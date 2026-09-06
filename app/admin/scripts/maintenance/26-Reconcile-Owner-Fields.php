<?php

declare(strict_types=1);

use ElanRegistry\DatabaseInterface;
use ElanRegistry\Exceptions\CarDatabaseException;
use ElanRegistry\Exceptions\OwnerDatabaseException;
use ElanRegistry\LogCategories;
use ElanRegistry\Owner;

/**
 * Reconcile Owner Fields Script (v2.30.1)
 *
 * Repeatable, admin-triggered maintenance job that detects and repairs drift
 * between the nine denormalized owner-contact columns on `cars` (email, fname,
 * lname, city, state, country, lat, lon, website) and their authoritative
 * source in `users` / `profiles`.
 *
 * Those columns are normally kept fresh by save-time hooks (the owner's own
 * profile save in `usersc/user_settings.php`, and the admin's manual
 * sync-location action). Save-time hooks are inherently incomplete: historical
 * rows, direct DB edits, imports, and any future write site that forgets to
 * call the sync all drift silently and stay drifted. This script is the
 * backstop that finds and repairs that drift on demand — it is not a
 * substitute for fixing a write site that fails to sync.
 *
 * Three-step flow (Analyze → Details → Execute), mirroring
 * `21-Fix-Page-Permissions.php`:
 *   1. Analyze  — read-only aggregate drift counts per field, plus an
 *                 informational count of cars whose `user_id` points at a
 *                 user row that no longer exists.
 *   2. Details  — read-only, paginated per-car before/after values.
 *   3. Execute  — loops the distinct owners with drift and calls
 *                 `Owner::syncOwnerFieldsToCars()` for each.
 *
 * IMPORTANT — no outer transaction. `Owner::syncOwnerFieldsToCars()` throws
 * `OwnerDatabaseException` when called inside an open transaction, because it
 * manages its own per-car transactions and an outer one would make every
 * per-car rollback a silent no-op. The execute loop below therefore must NOT
 * call `$db->beginTransaction()`, unlike `21-Fix-Page-Permissions.php`'s own
 * per-page update pattern.
 *
 * IMPORTANT — never writes `owner_last_updated`. A mechanical refresh is not
 * the owner confirming their car's data; leaving that column alone is what
 * keeps a synced car eligible for verification. `syncOwnerFieldsToCars()`
 * already honors this and must not be bypassed here.
 *
 * Orphaned `user_id` cars are reported in the Analyze summary only. Repairing
 * them is out of scope for a contact-field sync job, and they are excluded
 * from the owner-ID list that drives Execute.
 *
 * KNOWN PERFORMANCE CHARACTERISTIC: the drift queries are full scans over
 * `cars JOIN users LEFT JOIN profiles` with a nine-clause OR predicate — no
 * index supports that shape. Acceptable for an admin-triggered, infrequent job
 * at current fleet scale.
 *
 * Issue #1961.
 */
require_once '../../../../users/init.php';
require_once $abs_us_root . $us_url_root . 'app/admin/includes/fix-script-core.php';

if (!securePage($php_self)) {
    die();
}

if (!isAdmin()) {
    logger($user->data()->id, LogCategories::LOG_CATEGORY_SECURITY,
        'Non-admin attempted owner field reconciliation script');
    require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
    echo '<div class="alert alert-danger mt-3">Administrator access required.</div>';
    exit;
}

// Required as a global even though this file otherwise uses dbi():
// admin_script_record_completion() (fix-script-core.php) does `global $db`.
// Do not remove it as apparently-dead code.
$db = DB::getInstance();

if (!$db) {
    logger(0, LogCategories::LOG_CATEGORY_DATABASE_ERROR,
        '26-Reconcile-Owner-Fields: DB::getInstance() returned falsy — database connection unavailable');
    die('<div class="alert alert-danger" style="margin:2rem">Database connection failed. The maintenance script cannot run. Check application logs for details.</div>');
}

$csrfToken = Token::generate();

/**
 * Number of drifted cars returned per page by the Details step.
 */
const RECONCILE_DETAILS_PAGE_SIZE = 50;

/**
 * How many owners may fail in an unbroken row before the Execute loop treats
 * the failures as a systemic fault and aborts.
 *
 * Per-owner failures are absorbed so one bad owner cannot end a 100+-owner
 * run, but a fault that affects every owner alike (a dropped connection, a
 * schema change) would otherwise be retried once per owner — burying the real
 * error under volume and taking a long time to do it.
 */
const RECONCILE_MAX_CONSECUTIVE_OWNER_ERRORS = 5;

/**
 * The nine denormalized owner-contact columns, mapped to the source table
 * alias that holds their authoritative value (`u` = users, `p` = profiles).
 *
 * This is the single definition behind every drift-detection query in this
 * script, so the aggregate, owner-ID and per-car queries cannot drift apart.
 * It mirrors `Owner::ownerContactFields()`, which defines the same nine
 * columns on the write side.
 *
 * @var array<string, string>
 */
const RECONCILE_OWNER_FIELDS = [
    'email'   => 'u',
    'fname'   => 'u',
    'lname'   => 'u',
    'city'    => 'p',
    'state'   => 'p',
    'country' => 'p',
    'lat'     => 'p',
    'lon'     => 'p',
    'website' => 'p',
];

/**
 * The FROM/JOIN clause shared by every drift-detection query.
 *
 * `cars c JOIN users u` deliberately uses an inner join: a car whose `user_id`
 * has no matching user row is an orphan, counted separately by
 * {@see findOrphanedOwnerCarCount()} and never repaired here.
 */
const RECONCILE_FROM_CLAUSE = 'FROM cars c '
    . 'JOIN users u ON u.id = c.user_id '
    . 'LEFT JOIN profiles p ON p.user_id = u.id '
    . 'WHERE c.user_id > 0';

/**
 * Owner-contact fields whose columns are strings — `cars`'s string columns
 * collate `utf8mb4_unicode_ci` while `users`/`profiles` (upstream UserSpice
 * tables) collate `utf8mb4_general_ci`. Comparing them directly raises
 * MySQL's "Illegal mix of collations" error, which this project's
 * `DB::query()` swallows (returns a zero-row/error result rather than
 * throwing) — every drift comparison would silently report "no drift"
 * instead of failing loudly. `lat`/`lon` are `float` and never need this.
 *
 * Declared at file scope, not inside the `function_exists()` guard below:
 * PHP does not allow `const` inside a conditional block.
 *
 * @var list<string>
 */
const RECONCILE_STRING_FIELDS = ['email', 'fname', 'lname', 'city', 'state', 'country', 'website'];

if (!function_exists('reconcileFieldMismatchExpression')) {
    /**
     * SQL boolean expression that is true when one field differs between the
     * car row and its owner's authoritative source.
     *
     * Uses MySQL's null-safe `<=>` so a NULL on one side and a value on the
     * other counts as drift, while NULL on both sides does not. String fields
     * pin the comparison to `cars`'s collation explicitly (see
     * RECONCILE_STRING_FIELDS) to avoid the collation-mismatch error above.
     *
     * @param string $field One of the keys of RECONCILE_OWNER_FIELDS
     * @return string SQL fragment; built entirely from this script's own
     *                constants, never from user input
     */
    function reconcileFieldMismatchExpression(string $field): string
    {
        $sourceAlias = RECONCILE_OWNER_FIELDS[$field];
        $sourceExpr = in_array($field, RECONCILE_STRING_FIELDS, true)
            ? "{$sourceAlias}.{$field} COLLATE utf8mb4_unicode_ci"
            : "{$sourceAlias}.{$field}";

        return "NOT (c.{$field} <=> {$sourceExpr})";
    }
}

if (!function_exists('reconcileDriftPredicate')) {
    /**
     * SQL boolean expression that is true when a car differs from its owner in
     * at least one of the nine owner-contact fields.
     *
     * Defined once and reused by all three query sites so they cannot diverge.
     *
     * @return string SQL fragment; built entirely from this script's own
     *                constants, never from user input
     */
    function reconcileDriftPredicate(): string
    {
        $clauses = [];
        foreach (array_keys(RECONCILE_OWNER_FIELDS) as $field) {
            $clauses[] = reconcileFieldMismatchExpression($field);
        }

        return '(' . implode(' OR ', $clauses) . ')';
    }
}

if (!function_exists('findOwnerFieldDriftSummary')) {
    /**
     * Aggregate drift counts across the whole fleet.
     *
     * @param DatabaseInterface $db Database handle
     * @return array{fields: array<string, int>, carsWithDrift: int, ownersWithDrift: int}
     *         Per-field mismatch counts keyed by column name, the number of
     *         cars differing in at least one field, and the number of distinct
     *         owners those cars belong to.
     * @throws \RuntimeException If the query fails. `DB::query()` never throws
     *         on a failed statement — it returns an errored result with no
     *         rows — so an unchecked failure here would report "zero drift"
     *         instead of failing. That exact class of bug has already bitten
     *         this script twice (a collation mismatch and a LIMIT binding
     *         error), so every query in this file checks explicitly.
     */
    function findOwnerFieldDriftSummary(DatabaseInterface $db): array
    {
        $predicate = reconcileDriftPredicate();

        $selects = [];
        foreach (array_keys(RECONCILE_OWNER_FIELDS) as $field) {
            $selects[] = 'SUM(' . reconcileFieldMismatchExpression($field) . ") AS drift_{$field}";
        }
        $selects[] = "SUM({$predicate}) AS cars_with_drift";
        $selects[] = "COUNT(DISTINCT CASE WHEN {$predicate} THEN c.user_id END) AS owners_with_drift";

        $sql = 'SELECT ' . implode(', ', $selects) . ' ' . RECONCILE_FROM_CLAUSE;

        $result = $db->query($sql);
        if ($result->error()) {
            throw new \RuntimeException('Owner field drift summary query failed: ' . $result->errorString());
        }

        // A prepare()-level failure can leave DB's error flag unset, so the
        // absent row is the other half of the evidence: SELECT SUM(...) always
        // returns exactly one row, even over an empty table.
        $row = $result->first();
        if (!is_object($row)) {
            throw new \RuntimeException('Owner field drift summary returned no aggregate row.');
        }

        $fields = [];
        foreach (array_keys(RECONCILE_OWNER_FIELDS) as $field) {
            $fields[$field] = (int) ($row->{'drift_' . $field} ?? 0);
        }

        return [
            'fields'          => $fields,
            'carsWithDrift'   => (int) ($row->cars_with_drift ?? 0),
            'ownersWithDrift' => (int) ($row->owners_with_drift ?? 0),
        ];
    }
}

if (!function_exists('findOrphanedOwnerCarCount')) {
    /**
     * Count cars whose `user_id` references a user row that no longer exists.
     *
     * Reported for information only — these cars are never repaired by this
     * script and never appear in {@see findOwnerIdsWithDrift()}.
     *
     * @param DatabaseInterface $db Database handle
     * @return int Number of orphaned cars
     * @throws \RuntimeException If the query fails — see
     *         {@see findOwnerFieldDriftSummary()} for why an unchecked failure
     *         here would silently read as "no orphans".
     */
    function findOrphanedOwnerCarCount(DatabaseInterface $db): int
    {
        $result = $db->query(
            'SELECT COUNT(*) AS orphaned FROM cars c '
            . 'LEFT JOIN users u ON u.id = c.user_id '
            . 'WHERE c.user_id > 0 AND u.id IS NULL'
        );
        if ($result->error()) {
            throw new \RuntimeException('Orphaned owner car count query failed: ' . $result->errorString());
        }

        // As above: COUNT(*) always returns one row, so a missing row is
        // evidence of a prepare()-level failure the error flag did not catch.
        $row = $result->first();
        if (!is_object($row)) {
            throw new \RuntimeException('Orphaned owner car count returned no aggregate row.');
        }

        return (int) ($row->orphaned ?? 0);
    }
}

if (!function_exists('findOwnerIdsWithDrift')) {
    /**
     * The distinct owner IDs having at least one drifted car.
     *
     * This is the work list the Execute step iterates.
     *
     * @param DatabaseInterface $db Database handle
     * @return list<int> Owner (user) IDs, ascending. An empty list is a
     *         legitimate success result (no drift anywhere), which is why this
     *         function checks only `error()` and not row presence.
     * @throws \RuntimeException If the query fails — see
     *         {@see findOwnerFieldDriftSummary()}. An unchecked failure here
     *         would make Execute silently repair nothing.
     */
    function findOwnerIdsWithDrift(DatabaseInterface $db): array
    {
        $sql = 'SELECT DISTINCT c.user_id ' . RECONCILE_FROM_CLAUSE
            . ' AND ' . reconcileDriftPredicate()
            . ' ORDER BY c.user_id';

        $result = $db->query($sql);
        if ($result->error()) {
            throw new \RuntimeException('Owner drift work-list query failed: ' . $result->errorString());
        }

        $ownerIds = [];
        foreach ($result->results() as $row) {
            $ownerIds[] = (int) $row->user_id;
        }

        return $ownerIds;
    }
}

if (!function_exists('findDriftedCarDetails')) {
    /**
     * One page of per-car drift detail: the car's stored value and the owner's
     * current value, for each field that actually differs.
     *
     * `fields` is pruned — it holds only the subset of the nine owner-contact
     * columns that genuinely differ for that car, not all nine. It is never
     * empty: a row only reaches this output because the SQL drift predicate
     * already matched at least one differing field, hence
     * `non-empty-array` below. Callers must therefore iterate the keys present
     * rather than assuming a fixed nine-key shape.
     *
     * @param DatabaseInterface $db     Database handle
     * @param int               $limit  Maximum rows to return
     * @param int               $offset Rows to skip
     * @return list<array{carId: int, ownerId: int, ownerName: string, fields: non-empty-array<string, array{car: string|null, owner: string|null}>}>
     * @throws \RuntimeException If the query fails — see
     *         {@see findOwnerFieldDriftSummary()}. An unchecked failure here
     *         would render an empty "no drifted cars" table over real drift.
     */
    function findDriftedCarDetails(DatabaseInterface $db, int $limit, int $offset): array
    {
        $selects = ['c.id AS car_id', 'c.user_id AS owner_id', 'u.fname AS owner_fname', 'u.lname AS owner_lname'];
        foreach (RECONCILE_OWNER_FIELDS as $field => $sourceAlias) {
            $selects[] = "c.{$field} AS car_{$field}";
            $selects[] = "{$sourceAlias}.{$field} AS owner_{$field}";
        }

        // LIMIT/OFFSET are interpolated, not bound: this project's DB::query()
        // binds parameters as strings (PDO emulated-prepare default), and
        // MySQL rejects a quoted string in LIMIT/OFFSET position ("You have
        // an error in your SQL syntax ... near ''50' OFFSET '0''") — verified
        // against the live connection. Safe here because both are validated
        // non-negative integers from typed function parameters, never raw
        // user input reaching this string directly.
        $safeLimit = max(0, $limit);
        $safeOffset = max(0, $offset);

        $sql = 'SELECT ' . implode(', ', $selects) . ' ' . RECONCILE_FROM_CLAUSE
            . ' AND ' . reconcileDriftPredicate()
            . " ORDER BY c.user_id, c.id LIMIT {$safeLimit} OFFSET {$safeOffset}";

        $result = $db->query($sql);
        if ($result->error()) {
            throw new \RuntimeException('Drifted car detail query failed: ' . $result->errorString());
        }
        $rows = $result->results();

        $details = [];
        foreach ($rows as $row) {
            $fields = [];
            foreach (array_keys(RECONCILE_OWNER_FIELDS) as $field) {
                $carValue = $row->{'car_' . $field};
                $ownerValue = $row->{'owner_' . $field};
                // Both halves are needed: SQL NULL and '' stringify identically,
                // so the string comparison alone would prune a NULL-vs-'' pair
                // that the null-safe `<=>` predicate correctly counts as drift
                // (the sync writes one or the other deliberately).
                if ((string) $carValue === (string) $ownerValue && ($carValue === null) === ($ownerValue === null)) {
                    continue;
                }
                $fields[$field] = [
                    'car'   => $carValue === null ? null : (string) $carValue,
                    'owner' => $ownerValue === null ? null : (string) $ownerValue,
                ];
            }

            $details[] = [
                'carId'     => (int) $row->car_id,
                'ownerId'   => (int) $row->owner_id,
                'ownerName' => trim((string) ($row->owner_fname ?? '') . ' ' . (string) ($row->owner_lname ?? '')),
                'fields'    => $fields,
            ];
        }

        return $details;
    }
}

// AJAX handlers — must run before any HTML output.
if ($method === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (!Token::check($_POST['csrf'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid security token']);
        exit;
    }

    if (!isAdmin()) {
        http_response_code(403);
        logger($user->data()->id, LogCategories::LOG_CATEGORY_SECURITY,
            'Non-admin attempted AJAX action on owner field reconciliation script');
        echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
        exit;
    }

    if ($_POST['action'] === 'analyze') {
        try {
            $summary = findOwnerFieldDriftSummary(dbi());
            $orphanedCars = findOrphanedOwnerCarCount(dbi());

            $recordingWarning = null;
            if ($summary['carsWithDrift'] === 0) {
                admin_script_record_completion(__FILE__, (int) $user->data()->id, function (string $msg) use (&$recordingWarning) {
                    $recordingWarning = $msg;
                });
            }

            echo json_encode([
                'success'          => true,
                'fields'           => $summary['fields'],
                'carsWithDrift'    => $summary['carsWithDrift'],
                'ownersWithDrift'  => $summary['ownersWithDrift'],
                'orphanedCars'     => $orphanedCars,
                'pageSize'         => RECONCILE_DETAILS_PAGE_SIZE,
                'recordingWarning' => $recordingWarning,
            ]);
        } catch (\Throwable $e) {
            logger($user->data()->id, LogCategories::LOG_CATEGORY_FIX_SCRIPT_ERROR,
                'Owner field drift analysis failed: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error'   => 'Analysis failed. Check server logs for details.',
            ]);
        }
        exit;
    }

    if ($_POST['action'] === 'details') {
        try {
            $offset = max(0, (int) ($_POST['offset'] ?? 0));
            $details = findDriftedCarDetails(dbi(), RECONCILE_DETAILS_PAGE_SIZE, $offset);

            echo json_encode([
                'success'  => true,
                'offset'   => $offset,
                'pageSize' => RECONCILE_DETAILS_PAGE_SIZE,
                'hasMore'  => count($details) === RECONCILE_DETAILS_PAGE_SIZE,
                'cars'     => $details,
            ]);
        } catch (\Throwable $e) {
            logger($user->data()->id, LogCategories::LOG_CATEGORY_FIX_SCRIPT_ERROR,
                'Owner field drift detail lookup failed: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error'   => 'Failed to load details. Check server logs for details.',
            ]);
        }
        exit;
    }
}

require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

?>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="well">

            <style>
                .fix-results-container {
                    max-height: 500px;
                    overflow-y: auto;
                    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
                    font-size: 0.875rem;
                    line-height: 1.4;
                }

                .fix-status-line {
                    margin: 0.25rem 0;
                    padding: 0.125rem 0;
                }

                .reconcile-table {
                    font-size: 0.85rem;
                }

                .reconcile-table th {
                    background-color: #f8f9fa;
                    font-weight: 600;
                }
            </style>

            <!-- Initial Description Card -->
            <div class="row" id="descriptionSection">
                <div class="col-lg-12 mb-4">
                    <div class="card registry-card">
                        <div class="card-header">
                            <h2 class="mb-0">
                                <i class="fa fa-refresh"></i> Reconcile Owner Fields
                            </h2>
                        </div>
                        <div class="card-body">
                            <p class="mb-3">Finds cars whose denormalized owner-contact columns have drifted away from their owner's current <code>users</code> / <code>profiles</code> data, and repairs them.</p>

                            <div class="alert alert-info">
                                <h5><i class="fa fa-info-circle"></i> What this script does:</h5>
                                <ul class="mb-0">
                                    <li>Compares nine columns on each car — <code>email</code>, <code>fname</code>, <code>lname</code>, <code>city</code>, <code>state</code>, <code>country</code>, <code>lat</code>, <code>lon</code>, <code>website</code> — against the owner's current values</li>
                                    <li>Reports per-field drift counts, plus how many cars and owners are affected</li>
                                    <li>Repairs drift by calling the same owner-sync routine used when an owner saves their own profile</li>
                                    <li>Safe to run repeatedly — a run with no drift makes no changes</li>
                                </ul>
                            </div>

                            <div class="alert alert-warning">
                                <h5><i class="fa fa-exclamation-triangle"></i> What this script does <em>not</em> do:</h5>
                                <ul class="mb-0">
                                    <li>Does not touch <code>owner_last_updated</code> — a mechanical refresh is not the owner confirming their car's data, so synced cars stay eligible for verification</li>
                                    <li>Does not repair cars whose <code>user_id</code> points at a user that no longer exists — those are reported for information only</li>
                                    <li>Does not replace the save-time sync hooks; it is a backstop for drift those hooks missed</li>
                                </ul>
                            </div>

                            <div class="text-center">
                                <button data-action="startAnalysis" class="btn btn-success">
                                    <i class="fa fa-search"></i> Step 1: Analyze Drift
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analysis Results Section -->
            <div class="row" id="analysisSection" style="display: none;">
                <div class="col-lg-12 mb-4">
                    <div class="card registry-card">
                        <div class="card-header">
                            <h2 class="mb-0">
                                <i class="fa fa-list-alt"></i> Analysis Results
                            </h2>
                        </div>
                        <div class="card-body" id="analysisResults">
                            <div class="text-center">
                                <i class="fa fa-spinner fa-spin fa-3x"></i>
                                <p class="mt-3">Analyzing owner field drift...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Changes Section -->
            <div class="row" id="detailsSection" style="display: none;">
                <div class="col-lg-12 mb-4">
                    <div class="card registry-card">
                        <div class="card-header">
                            <h2 class="mb-0">
                                <i class="fa fa-list"></i> Drifted Cars
                            </h2>
                        </div>
                        <div class="card-body">
                            <div id="detailedChanges"></div>
                            <div id="detailsFooter" class="text-center mt-4"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Section -->
            <div class="row mb-4" id="progressSection" style="display: none;">
                <div class="col-lg-6 col-md-6">
                    <div class="card registry-card mb-4">
                        <div class="card-header">
                            <h2 class="mb-0">
                                <i class="fa fa-cogs"></i> Progress
                            </h2>
                            <small class="text-muted">
                                <i class="fa fa-clock-o"></i> Started: <span id="startTimeText"></span>
                            </small>
                        </div>
                        <div class="card-body">
                            <div class="progress car-progress mb-2">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                    id="progressBar"
                                    role="progressbar"
                                    style="width: 0%;"
                                    aria-valuenow="0"
                                    aria-valuemin="0"
                                    aria-valuemax="100">0%</div>
                            </div>
                            <div id="currentStatus" class="text-muted small">
                                <i class="fa fa-cog fa-spin"></i> Initializing...
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6">
                    <div class="card registry-card mb-4">
                        <div class="card-header">
                            <h2 class="mb-0">
                                <i class="fa fa-bar-chart"></i> Summary
                            </h2>
                        </div>
                        <div class="card-body" id="summaryContent">
                            <div class="text-muted">
                                <em>Waiting for process to complete...</em>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Log Section -->
            <div class="row mb-4" id="logSection" style="display: none;">
                <div class="col-12">
                    <div class="card registry-card">
                        <div class="card-header">
                            <h3 class="mb-0">
                                <i class="fa fa-list-alt"></i> Progress Log
                            </h3>
                        </div>
                        <div class="card-body fix-results-container" id="resultsContainer">
                            <div class="fix-status-line text-muted">
                                <small><em>Initializing process...</em></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <script nonce="<?= htmlspecialchars($userspice_nonce ?? '', ENT_QUOTES, 'UTF-8') ?>">
                let processStarted = false;
                let detailsOffset = 0;
                let detailsLoading = false;
                const CSRF_TOKEN = '<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>';
                const FIELD_LABELS = {
                    email: 'Email',
                    fname: 'First Name',
                    lname: 'Last Name',
                    city: 'City',
                    state: 'State',
                    country: 'Country',
                    lat: 'Latitude',
                    lon: 'Longitude',
                    website: 'Website'
                };

                function escapeHtml(s) {
                    if (s == null) return '';
                    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                }

                function formatValue(v) {
                    if (v == null) return '<em class="text-muted">NULL</em>';
                    if (v === '') return '<em class="text-muted">(empty)</em>';
                    return escapeHtml(v);
                }

                function updateProgress(current, total, statusMessage) {
                    if (total === 0) return;

                    const percentage = Math.round((current / total) * 100);
                    const progressBar = document.getElementById('progressBar');

                    progressBar.style.width = percentage + '%';
                    progressBar.setAttribute('aria-valuenow', percentage);
                    progressBar.textContent = percentage + '%';

                    if (statusMessage) {
                        const statusElement = document.getElementById('currentStatus');
                        const icon = document.createElement('i');
                        icon.className = percentage >= 100
                            ? 'fa fa-check-circle text-success'
                            : 'fa fa-cog fa-spin';
                        statusElement.replaceChildren(icon, document.createTextNode(' ' + statusMessage));
                    }
                }

                function showCompletionSummary(stats, isError) {
                    const statusText = isError
                        ? 'Reconciliation completed with errors.'
                        : 'Reconciliation completed successfully!';
                    updateProgress(100, 100, statusText);

                    const icon = isError
                        ? '<i class="fa fa-exclamation-triangle text-warning"></i>'
                        : '<i class="fa fa-check-circle text-success"></i>';
                    const title = isError ? 'Completed with Errors' : 'Complete!';

                    const summaryContent = document.getElementById('summaryContent');
                    summaryContent.innerHTML = `
        <div class="mb-3">
            <h5>${icon} ${title}</h5>
            <small class="text-muted">Completed at: ${new Date().toLocaleString()}</small>
        </div>
        <div class="mb-3">
            ${stats}
        </div>
        <div class="text-center">
            <button data-action="returnToMenu" class="btn btn-outline-primary">
                <i class="fa fa-arrow-left"></i> Return to FIX Menu
            </button>
        </div>
    `;
                }

                function addLogMessage(message) {
                    const container = document.getElementById('resultsContainer');
                    if (!container) return;

                    const line = document.createElement('div');
                    line.className = 'fix-status-line';

                    if (message.includes('✅')) {
                        line.className += ' text-success';
                    } else if (message.includes('✗') || message.includes('❌')) {
                        line.className += ' text-danger';
                    } else if (message.includes('===')) {
                        line.className += ' text-info font-weight-bold';
                    } else if (message.includes('Processing')) {
                        line.className += ' text-primary';
                    }

                    line.textContent = message;
                    container.appendChild(line);
                    container.scrollTop = container.scrollHeight;
                }

                function startAnalysis() {
                    document.getElementById('descriptionSection').style.display = 'none';
                    document.getElementById('analysisSection').style.display = 'block';

                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=analyze&csrf=' + encodeURIComponent(CSRF_TOKEN)
                    })
                    .then(response => response.json())
                    .then(data => {
                        const resultsElement = document.getElementById('analysisResults');

                        if (!data.success) {
                            resultsElement.innerHTML = `
                                <div class="alert alert-danger">
                                    <h4><i class="fa fa-exclamation-circle"></i> Analysis Error</h4>
                                    <p>${escapeHtml(data.error)}</p>
                                </div>
                                <div class="text-center">
                                    <button data-action="returnToMenu" class="btn btn-primary">
                                        <i class="fa fa-arrow-left"></i> Return to FIX Menu
                                    </button>
                                </div>
                            `;
                            return;
                        }

                        const orphanHtml = data.orphanedCars > 0
                            ? `<div class="alert alert-secondary"><i class="fa fa-unlink"></i> <strong>${data.orphanedCars}</strong> car(s) reference a user that no longer exists. These are reported for information only and are not repaired by this script.</div>`
                            : '';

                        if (data.carsWithDrift === 0) {
                            const recordingWarningHtml = data.recordingWarning
                                ? `<div class="alert alert-warning mt-2 mb-0">${escapeHtml(data.recordingWarning)}</div>`
                                : '';
                            resultsElement.innerHTML = `
                                <div class="alert alert-success">
                                    <h4><i class="fa fa-check-circle"></i> No Owner Field Drift Found!</h4>
                                    <p>Every car's owner-contact columns already match their owner's current details.</p>
                                </div>
                                ${orphanHtml}
                                ${recordingWarningHtml}
                                <div class="text-center">
                                    <button data-action="returnToMenu" class="btn btn-outline-primary">
                                        <i class="fa fa-arrow-left"></i> Return to FIX Menu
                                    </button>
                                </div>
                            `;
                            return;
                        }

                        let rows = '';
                        Object.keys(FIELD_LABELS).forEach(field => {
                            const count = data.fields[field] || 0;
                            if (count > 0) {
                                rows += `<tr><td>${escapeHtml(FIELD_LABELS[field])}</td><td><code>${escapeHtml(field)}</code></td><td class="text-end">${count}</td></tr>`;
                            }
                        });

                        resultsElement.innerHTML = `
                            <div class="alert alert-warning">
                                <h4><i class="fa fa-exclamation-triangle"></i> Drift Found</h4>
                                <p class="mb-0"><strong>${data.carsWithDrift}</strong> car(s) across <strong>${data.ownersWithDrift}</strong> owner(s) differ from their owner's current details.</p>
                            </div>
                            ${orphanHtml}
                            <table class="table table-sm table-bordered reconcile-table">
                                <thead><tr><th>Field</th><th>Column</th><th class="text-end">Cars Affected</th></tr></thead>
                                <tbody>${rows}</tbody>
                            </table>
                            <div class="text-center mt-4">
                                <button data-action="showDetailedChanges" class="btn btn-success">
                                    <i class="fa fa-arrow-right"></i> Step 2: Review Drifted Cars
                                </button>
                                <button data-action="abortProcess" class="btn btn-danger ms-2">
                                    <i class="fa fa-times"></i> Abort
                                </button>
                            </div>
                        `;
                    })
                    .catch(error => {
                        document.getElementById('analysisResults').innerHTML = `
                            <div class="alert alert-danger">
                                <h4><i class="fa fa-exclamation-circle"></i> Error</h4>
                                <p>Failed to fetch analysis: ${escapeHtml(error.message)}</p>
                            </div>
                        `;
                    });
                }

                function showDetailedChanges() {
                    document.getElementById('detailsSection').style.display = 'block';
                    detailsOffset = 0;
                    document.getElementById('detailedChanges').innerHTML = `
                        <table class="table table-sm table-bordered reconcile-table mb-0">
                            <thead><tr><th>Car</th><th>Owner</th><th>Field</th><th>Current Car Value</th><th>Owner's Value</th></tr></thead>
                            <tbody id="detailsBody"></tbody>
                        </table>
                    `;
                    document.getElementById('detailsFooter').innerHTML = '<i class="fa fa-spinner fa-spin fa-2x"></i>';
                    loadMoreDetails();
                }

                function loadMoreDetails() {
                    if (detailsLoading) return;
                    detailsLoading = true;

                    const footer = document.getElementById('detailsFooter');
                    footer.innerHTML = '<i class="fa fa-spinner fa-spin fa-2x"></i>';

                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=details&offset=' + encodeURIComponent(detailsOffset)
                            + '&csrf=' + encodeURIComponent(CSRF_TOKEN)
                    })
                    .then(response => response.json())
                    .then(data => {
                        detailsLoading = false;

                        if (!data.success) {
                            footer.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(data.error)}</div>`;
                            return;
                        }

                        const body = document.getElementById('detailsBody');
                        let html = '';
                        data.cars.forEach(car => {
                            const fieldNames = Object.keys(car.fields);
                            if (fieldNames.length === 0) return;
                            fieldNames.forEach((field, index) => {
                                const values = car.fields[field];
                                html += '<tr>';
                                if (index === 0) {
                                    html += `<td rowspan="${fieldNames.length}">${car.carId}</td>`;
                                    html += `<td rowspan="${fieldNames.length}">${escapeHtml(car.ownerName)}<br/><small class="text-muted">#${car.ownerId}</small></td>`;
                                }
                                html += `<td>${escapeHtml(FIELD_LABELS[field] || field)}</td>`;
                                html += `<td>${formatValue(values.car)}</td>`;
                                html += `<td>${formatValue(values.owner)}</td>`;
                                html += '</tr>';
                            });
                        });
                        body.insertAdjacentHTML('beforeend', html);

                        detailsOffset += data.cars.length;

                        const loadMoreHtml = data.hasMore
                            ? `<button data-action="loadMoreDetails" class="btn btn-outline-primary mb-3">
                                   <i class="fa fa-angle-double-down"></i> Load More (showing ${detailsOffset})
                               </button><br/>`
                            : `<p class="text-muted">Showing all ${detailsOffset} drifted car(s).</p>`;

                        footer.innerHTML = `
                            ${loadMoreHtml}
                            <div class="alert alert-info text-start">
                                <h5><i class="fa fa-info-circle"></i> Next Steps:</h5>
                                <ul class="mb-0">
                                    <li>Each affected owner's details are copied to every car they own</li>
                                    <li><code>owner_last_updated</code> is deliberately left unchanged</li>
                                    <li>Each car is written in its own transaction and audited in <code>cars_hist</code></li>
                                </ul>
                            </div>
                            <button data-action="startProcessing" class="btn btn-success btn-lg">
                                <i class="fa fa-play"></i> Step 3: Reconcile Owner Fields
                            </button>
                            <button data-action="abortProcess" class="btn btn-danger btn-lg ms-2">
                                <i class="fa fa-times"></i> Abort
                            </button>
                        `;
                    })
                    .catch(error => {
                        detailsLoading = false;
                        footer.innerHTML = `<div class="alert alert-danger mb-0">Failed to load details: ${escapeHtml(error.message)}</div>`;
                    });
                }

                function startProcessing() {
                    if (processStarted) return;
                    processStarted = true;

                    document.getElementById('progressSection').style.display = '';
                    document.getElementById('logSection').style.display = '';

                    document.getElementById('startTimeText').textContent = new Date().toLocaleString();
                    document.getElementById('resultsContainer').innerHTML = '';

                    document.getElementById('execute-form').submit();
                }

                function abortProcess() {
                    if (confirm('Are you sure you want to abort? No changes will be made.')) {
                        window.close();
                    }
                }

                document.addEventListener('click', function(e) {
                    const btn = e.target.closest('[data-action]');
                    if (!btn) return;
                    switch (btn.dataset.action) {
                        case 'startAnalysis': startAnalysis(); break;
                        case 'showDetailedChanges': showDetailedChanges(); break;
                        case 'loadMoreDetails': loadMoreDetails(); break;
                        case 'abortProcess': abortProcess(); break;
                        case 'startProcessing': startProcessing(); break;
                        case 'returnToMenu':
                            window.close();
                            break;
                    }
                });
            </script>

            <iframe id="execute-frame" name="execute-frame" style="display:none;"></iframe>
            <form id="execute-form" method="POST" action="" target="execute-frame">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="execute" value="1">
            </form>

            <?php
            // STEP 3: Execute reconciliation
            if ($method === 'POST' && isset($_POST['execute']) && Token::check($_POST['csrf'] ?? '')) {
                if (!isAdmin()) {
                    logger($user->data()->id, LogCategories::LOG_CATEGORY_SECURITY,
                        'Non-admin attempted execute form on owner field reconciliation script');
                    $nonce = htmlspecialchars($userspice_nonce ?? '', ENT_QUOTES, 'UTF-8');
                    $msg = json_encode('❌ Access denied: Administrator permission required.', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                    echo '<script nonce="' . $nonce . '">
                        if (window.parent && window.parent.addLogMessage) { window.parent.addLogMessage(' . $msg . '); }
                        else if (window.addLogMessage) { addLogMessage(' . $msg . '); }
                    </script>';
                    exit;
                }

                /**
                 * Streams one progress line (and optionally a progress-bar update)
                 * from this hidden iframe up to the parent page.
                 *
                 * @param string   $message    Line to append to the progress log
                 * @param int|null $percentage Progress percentage, or null to leave the bar alone
                 */
                function outputMessage(string $message, ?int $percentage = null): void {
                    global $userspice_nonce;
                    $jsMessage = json_encode($message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                    $nonce = htmlspecialchars($userspice_nonce ?? '', ENT_QUOTES, 'UTF-8');
                    echo '<script nonce="' . $nonce . '">
                        if (window.parent && window.parent.addLogMessage) {
                            window.parent.addLogMessage(' . $jsMessage . ');
                        } else if (window.addLogMessage) {
                            addLogMessage(' . $jsMessage . ');
                        }
                    </script>';
                    if ($percentage !== null) {
                        echo '<script nonce="' . $nonce . '">
                            if (window.parent && window.parent.updateProgress) {
                                window.parent.updateProgress(' . $percentage . ', 100, ' . $jsMessage . ');
                            } else if (window.updateProgress) {
                                updateProgress(' . $percentage . ', 100, ' . $jsMessage . ');
                            }
                        </script>';
                    }
                    ob_flush();
                    flush();
                }

                $ownersScanned = 0;
                $carsUpdated = 0;
                $carsSkipped = 0;
                $carsFailed = 0;
                $ownerErrors = 0;
                $consecutiveOwnerErrors = 0;
                // Initialized here as well as inside the try below, so a failure
                // at the owner-ID fetch step still leaves it defined for the
                // summary output that reads it afterwards.
                $totalOwners = 0;
                $scriptFailed = false;
                $actingUserId = (int) $user->data()->id;

                try {
                    outputMessage('🔍 Finding owners with drifted cars...');
                    $ownerIds = findOwnerIdsWithDrift(dbi());
                    $totalOwners = count($ownerIds);

                    if ($totalOwners === 0) {
                        outputMessage('✅ No drift found — nothing to reconcile.');
                    } else {
                        outputMessage("Found {$totalOwners} owner(s) with at least one drifted car");
                        outputMessage('');
                        outputMessage('=== Reconciling Owner Fields ===');
                    }

                    foreach ($ownerIds as $ownerId) {
                        $ownersScanned++;
                        $percentage = (int) round(($ownersScanned / max(1, $totalOwners)) * 100);

                        // NO outer transaction here: syncOwnerFieldsToCars() manages its own
                        // per-car transactions and throws if one is already open.
                        try {
                            $result = (new Owner($ownerId, dbi()))->syncOwnerFieldsToCars();

                            $carsUpdated += $result->updatedCount();
                            $carsSkipped += $result->skippedCount();
                            $carsFailed  += $result->failedCount();

                            $consecutiveOwnerErrors = 0;

                            if ($result->isCompleteSuccess()) {
                                outputMessage("✅ Owner {$ownerId}: " . $result->successMessage(), $percentage);
                            } else {
                                outputMessage("✗ Owner {$ownerId}: " . $result->failedCarsPhrase(), $percentage);
                                // Name the cars, not just how many: a later debugging
                                // session should not have to cross-reference by timestamp.
                                $failedIds = $result->failed === [] ? 'none' : implode(', ', $result->failed);
                                logger($actingUserId, LogCategories::LOG_CATEGORY_FIX_SCRIPT_ERROR,
                                    "Owner field reconciliation: owner {$ownerId} had {$result->failedCount()} "
                                    . "failed car(s) — car IDs: {$failedIds}");
                            }
                        } catch (OwnerDatabaseException | CarDatabaseException $e) {
                            // Only infrastructure-level failures are absorbed here. One
                            // owner's DB trouble must not abort a run that may cover 100+
                            // owners. Any OTHER \Throwable is a programming error in this
                            // script (a \TypeError, a leaked transaction) that will repeat
                            // identically for every remaining owner, so it deliberately
                            // propagates to the outer handler and aborts the run.
                            $ownerErrors++;
                            $consecutiveOwnerErrors++;

                            // A failure thrown mid-transaction could leave one open, which
                            // would then trip syncOwnerFieldsToCars()'s inTransaction()
                            // guard for every subsequent owner. Close it here so one
                            // failure cannot cascade.
                            if (dbi()->inTransaction()) {
                                dbi()->rollBack();
                            }

                            outputMessage("❌ Owner {$ownerId} failed: " . get_class($e) . ': ' . $e->getMessage(), $percentage);
                            logger($actingUserId, LogCategories::LOG_CATEGORY_FIX_SCRIPT_ERROR,
                                "Owner field reconciliation failed for owner {$ownerId}: " . $e->getMessage());

                            if ($consecutiveOwnerErrors >= RECONCILE_MAX_CONSECUTIVE_OWNER_ERRORS) {
                                throw new \RuntimeException(
                                    RECONCILE_MAX_CONSECUTIVE_OWNER_ERRORS . ' consecutive owners failed to sync, which '
                                    . 'indicates a systemic fault rather than per-owner trouble — aborting rather than '
                                    . 'retrying it for every remaining owner. Last error: ' . $e->getMessage(),
                                    0,
                                    $e
                                );
                            }
                        }
                    }

                    outputMessage('');
                    outputMessage("Owners scanned: {$ownersScanned} | Cars updated: {$carsUpdated} | "
                        . "Cars skipped: {$carsSkipped} | Cars failed: {$carsFailed} | Owner errors: {$ownerErrors}");

                    logger($actingUserId, LogCategories::LOG_CATEGORY_DATABASE_MAINTENANCE,
                        "Owner field reconciliation completed — owners scanned: {$ownersScanned}, "
                        . "cars updated: {$carsUpdated}, cars skipped: {$carsSkipped}, "
                        . "cars failed: {$carsFailed}, owner-level errors: {$ownerErrors}");
                } catch (\Throwable $e) {
                    $scriptFailed = true;
                    outputMessage('❌ ERROR during reconciliation: ' . $e->getMessage());
                    outputMessage("Progress before failure: {$ownersScanned} owner(s) processed, {$carsUpdated} car(s) updated");
                    try {
                        logger($actingUserId, LogCategories::LOG_CATEGORY_FIX_SCRIPT_ERROR,
                            'Owner field reconciliation aborted: ' . $e->getMessage());
                    } catch (\Throwable $_) {
                        // Secondary failure — logger unavailable, ignore silently
                    }
                }

                $hadErrors = $scriptFailed || $ownerErrors > 0 || $carsFailed > 0;

                $recordingFailed = false;
                admin_script_record_completion(__FILE__, $actingUserId, function (string $msg) use (&$recordingFailed) {
                    $recordingFailed = true;
                    outputMessage($msg);
                });
                if (!$recordingFailed) {
                    outputMessage($hadErrors ? '⚠️  Script completion recorded (with errors)' : '✅ Script completion recorded');
                }

                outputMessage('');
                if (!$scriptFailed) {
                    outputMessage($hadErrors
                        ? 'Script completed with errors at ' . date('h:i:sa')
                        : 'Script completed at ' . date('h:i:sa'));
                }

                if (!$scriptFailed) {
                    $isErrorJs = $hadErrors ? 'true' : 'false';
                    $statsHtml = "
            <div class='row'>
                <div class='col-sm-6'><strong>Owners Scanned:</strong> {$ownersScanned}</div>
                <div class='col-sm-6'><strong>Cars Updated:</strong> {$carsUpdated}</div>
                <div class='col-sm-6'><strong>Cars Skipped:</strong> {$carsSkipped}</div>
                <div class='col-sm-6'><strong>Cars Failed:</strong> {$carsFailed}</div>
                <div class='col-sm-12'><strong>Owner-Level Errors:</strong> {$ownerErrors}</div>
            </div>
        ";
                    echo '<script nonce="' . htmlspecialchars($userspice_nonce ?? '', ENT_QUOTES, 'UTF-8') . '">
    if (window.parent && window.parent.showCompletionSummary) {
        window.parent.showCompletionSummary(`' . $statsHtml . '`, ' . $isErrorJs . ');
    } else if (window.showCompletionSummary) {
        showCompletionSummary(`' . $statsHtml . '`, ' . $isErrorJs . ');
    }
    </script>';
                }
            }

            ?>

        </div> <!-- well -->
    </div><!-- Container -->
</div> <!-- page-wrapper -->

<!-- Return buttons -->
<div style="margin-top: 20px; text-align: center;">
    <?= admin_script_close_button() ?>
    <button data-action="returnToMenu" class="btn btn-outline-secondary ms-2">
        <i class="fa fa-list" aria-hidden="true"></i> FIX Menu
    </button>
</div>

<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; ?>
