<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/../../app/admin/includes/fix-script-core.php';

use PHPUnit\Framework\Attributes\Group;

/**
 * Integration test for #1776: 21-Fix-Page-Permissions.php's `analyze` AJAX
 * action must record a `fix_script_runs` completion row when it finds zero
 * outstanding permission issues.
 *
 * `21-Fix-Page-Permissions.php` is a full page script gated by a top-level
 * `securePage($php_self)` check (and, for its AJAX branch, CSRF + `isAdmin()`
 * checks) before any of its functions are reachable. `securePage()` itself
 * mutates `pages`/`permission_page_matches` and can call `Redirect::to()` /
 * `die()` depending on ambient DB state — there is no precedent anywhere in
 * this suite for `require`-ing a `securePage()`-gated admin script directly
 * to reach its internals, and doing so here would make this test's outcome
 * depend on incidental `pages` row state rather than the fix under test.
 *
 * Instead, this test calls the same two calls the fix wires together,
 * directly, in the same order the AJAX branch performs them:
 *
 *   1. `analyzePermissions(dbi())` — the real function from
 *      21-Fix-Page-Permissions.php (loaded once, guarded by
 *      function_exists(), see loadAnalyzePermissions() below).
 *   2. `admin_script_record_completion(__FILE__, $userId)` — the real shared
 *      helper from fix-script-core.php, given a zero-issue result, exactly
 *      as the script's `if ($totalIssues === 0)` branch does.
 *
 * This proves: (a) analyzePermissions() genuinely returns zero issues against
 * a correctly-classified `pages` table, and (b) admin_script_record_completion()
 * genuinely inserts a `fix_script_runs` row for this script's basename. It
 * does NOT prove that 21-Fix-Page-Permissions.php's own AJAX handler body
 * actually reaches and executes its `admin_script_record_completion(...)`
 * call at runtime — that would require exercising the full HTTP-gated script,
 * which this suite has no harness for. Given the fix is a single `if` guard
 * calling an already-covered helper with an already-covered function's
 * output, this is judged adequate: a regression that deleted or
 * mis-conditioned that call would not be caught by this test, but a
 * regression in either collaborator's own behavior would be.
 */
#[Group('integration')]
final class FixPagePermissionsAnalyzeRunTest extends IntegrationTestCase
{
    private const SCRIPT_BASENAME = '21-Fix-Page-Permissions.php';

    private const SCRIPT_PATH = __DIR__ . '/../../app/admin/scripts/maintenance/21-Fix-Page-Permissions.php';

    /** @var list<int> fix_script_runs.id values inserted by this test, deleted in tearDown(). */
    private array $insertedRunIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();
        $this->loadAnalyzePermissions();
    }

    protected function tearDown(): void
    {
        if ($this->databaseConnected) {
            foreach ($this->insertedRunIds as $runId) {
                try {
                    $this->db->delete('fix_script_runs', ['id', '=', $runId]);
                } catch (RuntimeException $e) {
                    fwrite(STDERR, "NOTE: tearDown() cleanup failed for fix_script_runs id {$runId}: {$e->getMessage()}\n");
                }
            }
        }

        parent::tearDown();
    }

    /**
     * Reproduces the AJAX `analyze` action's own zero-issues branch
     * (21-Fix-Page-Permissions.php lines ~358-369) against the real `pages` /
     * `permission_page_matches` state of the integration test database, then
     * asserts the fix under test: a fresh `fix_script_runs` row for this
     * script.
     *
     * If the current test-schema `pages` table is not already in a
     * zero-issues state (e.g. a prior migration/seed drift), the test skips
     * rather than asserting a false pass/fail on unrelated classification
     * drift — that state is PageRegistrationSeedTest's concern, not this
     * test's.
     */
    public function testZeroIssuesAnalysisRunRecordsFixScriptRunsCompletion(): void
    {
        $userId = $this->createTestUser();

        $issues = analyzePermissions(dbi());
        $totalIssues = count($issues['set_public']) + count($issues['set_private_admin']) +
            count($issues['set_private_user']) + count($issues['set_private_no_perms']) +
            count($issues['remove_perms']) + count($issues['add_perms_admin']) +
            count($issues['add_perms_user']) + count($issues['fix_admin_only_perms']);

        if ($totalIssues !== 0) {
            $this->markTestSkipped(
                'Test database pages table is not currently in a zero-issues state '
                . "(totalIssues={$totalIssues}) — cannot exercise the zero-issues completion path. "
                . 'Run the PageRegistrationSeed (composer migrate) to restore a clean pages table.'
            );
        }

        $beforeMax = $this->maxRunId();

        admin_script_record_completion(self::SCRIPT_PATH, $userId);

        $rows = $this->db->query(
            'SELECT id, script_name, completed_at FROM fix_script_runs WHERE id > ? ORDER BY id DESC',
            [$beforeMax]
        )->results();

        $this->assertNotEmpty($rows, 'admin_script_record_completion() must insert a fix_script_runs row');

        $row = $rows[0];
        $this->insertedRunIds[] = (int) $row->id;

        $this->assertSame(
            self::SCRIPT_BASENAME,
            $row->script_name,
            'fix_script_runs.script_name must be the basename of 21-Fix-Page-Permissions.php'
        );

        $completedAt = strtotime((string) $row->completed_at);
        $this->assertNotFalse($completedAt, 'completed_at must be a parseable timestamp');
        $this->assertGreaterThan(
            time() - 60,
            $completedAt,
            'completed_at must be a fresh timestamp recorded by this test run, not a stale row'
        );
    }

    private function maxRunId(): int
    {
        $row = $this->db->query('SELECT COALESCE(MAX(id), 0) AS max_id FROM fix_script_runs')->first();

        return is_object($row) ? (int) $row->max_id : 0;
    }

    /**
     * Loads analyzePermissions() from 21-Fix-Page-Permissions.php without
     * executing the rest of that file (top-level securePage() gate, AJAX
     * request handling, HTML template render) — none of which this test can
     * safely trigger outside a real HTTP request.
     *
     * PagePermissionClassifier (autoloaded via Composer PSR-4) already has
     * its own dedicated unit suite (tests/unit/admin/PagePermissionClassifierTest.php)
     * covering the classification rules analyzePermissions() delegates to;
     * this test only needs analyzePermissions() itself, which is why it's
     * extracted this way rather than requiring the whole file.
     */
    private function loadAnalyzePermissions(): void
    {
        if (function_exists('analyzePermissions')) {
            return;
        }

        $source = file_get_contents(self::SCRIPT_PATH);
        if ($source === false) {
            throw new RuntimeException('Could not read ' . self::SCRIPT_PATH);
        }

        // Isolate the analyzePermissions() function body: from its own
        // declaration up to (but not including) the "Handle POST requests
        // for AJAX" line that starts the securePage()-gated request handling
        // this test must never execute.
        // Starts at the shouldBePrivateNoPermissions() wrapper (the first of four
        // thin file-local wrappers analyzePermissions() itself calls, each just
        // delegating to PagePermissionClassifier — see that file's own docblocks),
        // so those wrappers are included in the extracted slice alongside
        // analyzePermissions() itself.
        $startMarker = "/** @see PagePermissionClassifier::shouldBePrivateNoPermissions() */";
        $endMarker = "// Handle POST requests for AJAX";

        $startPos = strpos($source, $startMarker);
        $endPos = strpos($source, $endMarker);

        if ($startPos === false || $endPos === false || $endPos <= $startPos) {
            throw new RuntimeException(
                'Could not locate analyzePermissions() in ' . self::SCRIPT_PATH
                . ' — the script may have been restructured; update loadAnalyzePermissions() to match.'
            );
        }

        $functionSource = substr($source, $startPos, $endPos - $startPos);

        // Written to a real temp file and require()'d rather than eval()'d: this
        // keeps the extracted analyzePermissions() body subject to normal PHP
        // file compilation/opcache semantics like any other included file,
        // instead of executing a runtime-built string.
        $tempFile = tempnam(sys_get_temp_dir(), 'analyzePermissions_');
        if ($tempFile === false) {
            throw new RuntimeException('Could not create temp file for analyzePermissions() extraction');
        }

        // PERM_USER / PERM_ADMIN / PERM_EDITOR are define()'d earlier in the real
        // file (outside this extracted slice) and read by analyzePermissions();
        // reproduce them here with the same values rather than widening the slice
        // to also swallow the surrounding $db/security-header setup code.
        $preamble = "<?php\n"
            . "declare(strict_types=1);\n"
            . "use ElanRegistry\\Admin\\PagePermissionClassifier;\n"
            . "use ElanRegistry\\DatabaseInterface;\n"
            . "if (!defined('PERM_USER')) { define('PERM_USER', 1); }\n"
            . "if (!defined('PERM_ADMIN')) { define('PERM_ADMIN', 2); }\n"
            . "if (!defined('PERM_EDITOR')) { define('PERM_EDITOR', 3); }\n";

        $written = file_put_contents($tempFile, $preamble . $functionSource);
        if ($written === false) {
            unlink($tempFile);
            throw new RuntimeException('Could not write extracted analyzePermissions() source to temp file');
        }

        try {
            require $tempFile;
        } finally {
            unlink($tempFile);
        }
    }
}
