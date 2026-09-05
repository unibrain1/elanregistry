<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Issue #1953: convert the car timestamp columns from TIMESTAMP to DATETIME and
 * make `cars.owner_last_updated` NOT NULL, so verification freshness no longer
 * needs a `COALESCE(owner_last_updated, mtime)` fallback.
 *
 * Six columns convert TIMESTAMP -> DATETIME:
 *
 * - `cars.ctime`, `cars.mtime`, `cars.last_verified`
 * - `cars_hist.ctime`, `cars_hist.mtime`, `cars_hist.timestamp`
 *
 * and `cars.owner_last_updated` moves from `DATETIME NULL` to
 * `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` (with **no** `ON UPDATE`
 * clause — its absence is the entire point of the issue: the column must record
 * owner activity only, never incidental row writes).
 *
 * `cars_hist.timestamp` is deliberately included even though nothing in the
 * freshness expression reads it. ADR-003 makes `cars_hist` a type-for-type
 * mirror of `cars`, and every trigger fire writes `NEW.ctime`/`NEW.mtime`
 * (now DATETIME) into `cars_hist`. Leaving any `cars_hist` timestamp column as
 * TIMESTAMP would reintroduce a silent DATETIME->TIMESTAMP conversion on every
 * audit row — precisely what this migration removes — and would fail outright
 * on any value past 2038-01-19. `cars_hist.timestamp` is converted alongside
 * its siblings so the audit table has no remaining TIMESTAMP column and no
 * mixed-type surprise for a future reader. Its `idx_cars_hist_timestamp` index
 * must survive the conversion, which is why raw `MODIFY COLUMN` is used rather
 * than Phinx's `changeColumn()` (see "Why raw SQL" below).
 *
 * SESSION TIMEZONE ASSUMPTION (the highest-risk property of this migration).
 * `ALTER TABLE ... MODIFY COLUMN <ts> DATETIME` reads each stored TIMESTAMP
 * (held internally as UTC), renders it as a wall-clock string **in the
 * session's `time_zone`**, and stores that literal verbatim. The rendered
 * string is therefore correct only if the session zone matches the zone the
 * application writes and reads its dates in. This application sets no timezone
 * anywhere — no `date_default_timezone_set()`, no `SET time_zone`, no `.env`
 * key — so PHP and MySQL are expected to agree by both inheriting the host.
 * Where they do not, every converted value shifts permanently and silently, and
 * the shifted values are still well-formed, so no later check catches it.
 * {@see assertClockAlignment()} enforces the assumption before any ALTER runs.
 *
 * up() + down() are used instead of change() because this migration mixes
 * column-type changes with a one-time data repair, a corrective backfill, and
 * trigger rebuilds, none of which Phinx can auto-reverse. See
 * database/migrations/README.md — "Only fall back to explicit up() + down()".
 *
 * Why raw SQL rather than Phinx's `changeColumn()`: the exact `ON UPDATE
 * CURRENT_TIMESTAMP` / `DEFAULT` / nullability triple differs per column here
 * (`cars.mtime` keeps `ON UPDATE`, `cars.owner_last_updated` must not gain
 * one, `cars.mtime` is NOT NULL while `cars_hist.mtime` is NULL), and a
 * TIMESTAMP->DATETIME `changeColumn()` can silently drop a dependent index such
 * as `idx_cars_hist_timestamp`. `MODIFY COLUMN` states the full target
 * definition explicitly, so what is written is what is applied.
 *
 * NOT ATOMIC: MySQL issues an implicit commit on every DDL statement, so the
 * `ALTER TABLE` and `CREATE TRIGGER` steps below cannot be wrapped in a
 * transaction. Only the date repair and the backfill are transactional. A
 * failure part-way therefore leaves the schema partly converted. Re-running is
 * safe; every step is individually guarded:
 *
 * 1. {@see assertClockAlignment()} is re-evaluated from scratch on each run and
 *    aborts before touching anything if the clocks disagree. A migration that
 *    throws is never recorded in `phinxlog`, so Phinx retries it on the next
 *    run once the environment is corrected.
 * 2. Every `MODIFY COLUMN` is idempotent: re-applying the identical target
 *    definition to an already-DATETIME column is a no-op ALTER, and applying it
 *    to a still-TIMESTAMP column completes the conversion. No `hasColumn()`
 *    guard is needed because no column is added or dropped.
 * 3. The partial-date repair is predicated on `DAY(...) = 0 OR MONTH(...) = 0`,
 *    which matches nothing once the rows are normalized.
 * 4. The backfill is unconditional over active rows and idempotent in effect:
 *    re-running rewrites the same rows to a value derived from `NOW()`, which
 *    remains comfortably older than the one-year freshness boundary.
 * 5. Triggers use `DROP TRIGGER IF EXISTS` followed by `CREATE TRIGGER`, and
 *    {@see assertCarsTriggersPresent()} turns a silently-missing trigger into a
 *    loud failure rather than an unaudited `cars` table.
 *
 * ONE-WAY STEPS — `down()` deliberately does not reverse these:
 *
 * - **The partial-date repair.** The 15 `cars_hist` rows carrying a zero day or
 *   zero month (`1999-06-00`, `2001-00-00`) cannot be restored: the target
 *   `sql_mode` (`NO_ZERO_IN_DATE`) rejects writing such values back, and the
 *   repair is not injective anyway — a normalized `1999-06-01` is
 *   indistinguishable from a row that genuinely read `1999-06-01` all along.
 *   The repair loses no information (it promotes an unknown component to `01`,
 *   the convention already applied to the live `cars` table by a prior repair)
 *   and is correct to keep after a rollback.
 * - **The backfill.** The prior `owner_last_updated` values are overwritten in
 *   place and are not journaled anywhere, so there is nothing to restore. They
 *   were themselves wrong — copied from `mtime` by 20260902104755, which is the
 *   defect this migration corrects — so restoring them would be undesirable
 *   even if it were possible. A rollback therefore returns the schema to its
 *   prior shape while leaving the corrected data in place.
 */
final class ConvertCarTimestampsToDatetime extends AbstractMigration
{
    /**
     * Maximum tolerated difference, in seconds, between MySQL's clock and PHP's
     * clock before the conversion is refused. Generous enough to absorb the
     * round-trip and any sub-minute NTP drift, far tighter than the smallest
     * real timezone offset (15 minutes).
     */
    private const CLOCK_SKEW_TOLERANCE_SECONDS = 120;

    /**
     * The corrective backfill, extracted verbatim so the integration test can
     * execute this exact statement rather than duplicating the string.
     *
     * Every **active** row (`solddate IS NULL`) is set unconditionally to just
     * past the one-year freshness boundary, so the whole active registry reads
     * as due-for-verification on day one.
     *
     * Unconditional is deliberate, and each half matters:
     *
     * - It must **overwrite** the values 20260902104755 backfilled from
     *   `mtime`. Measured against production, 93.7% of active cars carry an
     *   `mtime` inside the last year — from routine fix-script maintenance, not
     *   owner activity — so those inherited values make the registry read as
     *   freshly-confirmed and would suppress ~94% of the verification email
     *   this system exists to send, while logging successful cron runs nightly.
     * - It must not be narrowed to `WHERE last_verified IS NULL` or
     *   `WHERE owner_last_updated IS NULL`. Any such guard leaves the skipped
     *   rows carrying the new column default — the migration's own run time —
     *   freezing them as falsely fresh for a full year.
     *
     * `mtime = mtime` is load-bearing, not a no-op: `cars.mtime` is declared
     * `ON UPDATE CURRENT_TIMESTAMP`, so any `UPDATE` touching a row silently
     * rewrites `mtime` to the migration's run time and destroys every real
     * modification timestamp in the table. Omitting the column from `SET` does
     * **not** suppress the clause; assigning it explicitly does. Precedent:
     * 20260902104755:68-75.
     */
    public const BACKFILL_SQL = 'UPDATE cars'
        . ' SET owner_last_updated = DATE_SUB(NOW(), INTERVAL 366 DAY), mtime = mtime'
        . ' WHERE solddate IS NULL';

    public function up(): void
    {
        // --- 1. Refuse to convert under a mismatched clock -----------------
        // Must run before any ALTER: the conversion is irreversible in effect
        // (a shifted value is still well-formed and no later check detects it).
        $this->assertClockAlignment();

        // --- 2. Convert the `cars` columns --------------------------------
        // owner_last_updated: NOT NULL with a CURRENT_TIMESTAMP default and
        // deliberately NO `ON UPDATE` clause. The default only covers rows
        // inserted after this migration; existing rows are corrected by the
        // backfill in step 4. NOT NULL is what removes the need for the
        // COALESCE fallback in the freshness expression.
        $this->execute(
            'ALTER TABLE `cars` MODIFY COLUMN `owner_last_updated` '
            . 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
        );

        // ctime / last_verified stay nullable; mtime stays NOT NULL and keeps
        // both its DEFAULT and its ON UPDATE clause. ON UPDATE
        // CURRENT_TIMESTAMP is legal on DATETIME but is silently lost if the
        // new definition omits it, which is why it is restated here.
        $this->execute('ALTER TABLE `cars` MODIFY COLUMN `ctime` DATETIME NULL');
        $this->execute(
            'ALTER TABLE `cars` MODIFY COLUMN `mtime` '
            . 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        );
        $this->execute('ALTER TABLE `cars` MODIFY COLUMN `last_verified` DATETIME NULL');

        // --- 3. Repair partial dates before the cars_hist ALTER ------------
        $this->normalizePartialHistoryDates();

        // --- 4. Convert the `cars_hist` columns ---------------------------
        // The nullability asymmetry against `cars` is deliberate and preserved:
        // `cars_hist.mtime` is NULL while `cars.mtime` is NOT NULL, because a
        // history row records whatever the source row held, including nothing.
        // `cars_hist.timestamp` is NOT NULL with a CURRENT_TIMESTAMP default
        // and carries `idx_cars_hist_timestamp`; MODIFY COLUMN preserves the
        // index, which a Phinx changeColumn() on a TIMESTAMP can silently drop.
        $this->execute('ALTER TABLE `cars_hist` MODIFY COLUMN `ctime` DATETIME NULL');
        $this->execute('ALTER TABLE `cars_hist` MODIFY COLUMN `mtime` DATETIME NULL');
        $this->execute(
            'ALTER TABLE `cars_hist` MODIFY COLUMN `timestamp` '
            . 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
        );

        // --- 5. Corrective backfill ---------------------------------------
        // @disable_triggers suppresses cars_update for this UPDATE; without it
        // the trigger fires once per active row, flooding cars_hist with bogus
        // 'UPDATE' entries for internal bookkeeping that is not a real edit —
        // the project-wide escape hatch also used by bulk maintenance scripts.
        $adapter = $this->getAdapter();
        $adapter->beginTransaction();
        $this->execute('SET @disable_triggers = 1');

        try {
            $this->execute(self::BACKFILL_SQL);
            $adapter->commitTransaction();
        } catch (\Throwable $e) {
            $adapter->rollbackTransaction();
            throw $e;
        } finally {
            // @disable_triggers is a SESSION variable: it survives both the
            // failed statement and the rollback. Leaving it set would silently
            // disable `cars` auditing for every later write on this connection
            // — including a manual replay during partial-failure recovery,
            // which this migration's docblock explicitly plans for.
            $this->execute('SET @disable_triggers = NULL');
        }

        // --- 6. Rebuild the audit triggers --------------------------------
        // Rebuilt only after BOTH tables are converted, so a trigger is never
        // installed against a half-converted pair.
        //
        // MySQL does not strictly require a rebuild for MODIFY COLUMN (that
        // restriction applies to DROP/RENAME), but ADR-003's Trigger
        // Maintenance procedure mandates one for any `cars` schema change, and
        // it guarantees the bodies re-parse against the new column types rather
        // than running from a cached definition.
        //
        // These are the POST-20260902104755 bodies, carrying
        // owner_last_updated / vericode_sent_at / email_bounced. Reverting to
        // the baseline bodies here would silently stop auditing all three
        // verification columns — the single biggest hazard in this migration.
        $this->createTriggers(
            'owner_last_updated, vericode_sent_at, email_bounced',
            'NEW.owner_last_updated, NEW.vericode_sent_at, NEW.email_bounced',
            'OLD.owner_last_updated, OLD.vericode_sent_at, OLD.email_bounced'
        );
        $this->assertCarsTriggersPresent();
    }

    public function down(): void
    {
        // Reverse order of up(). The clock guard applies equally here: a
        // DATETIME -> TIMESTAMP conversion renders the stored literal in the
        // session zone to derive a UTC instant, so a mismatched clock shifts
        // every value on the way back out too.
        $this->assertClockAlignment();

        // --- 1. Restore the cars_hist column types ------------------------
        $this->execute('ALTER TABLE `cars_hist` MODIFY COLUMN `ctime` TIMESTAMP NULL');
        $this->execute('ALTER TABLE `cars_hist` MODIFY COLUMN `mtime` TIMESTAMP NULL');
        $this->execute(
            'ALTER TABLE `cars_hist` MODIFY COLUMN `timestamp` '
            . 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
        );

        // --- 2. Restore the cars column types -----------------------------
        // The pre-migration definitions exactly: ctime and last_verified
        // nullable with no default and no extra; mtime NOT NULL with both
        // DEFAULT and ON UPDATE; owner_last_updated back to a plain nullable
        // DATETIME with no default.
        $this->execute('ALTER TABLE `cars` MODIFY COLUMN `last_verified` TIMESTAMP NULL');
        $this->execute(
            'ALTER TABLE `cars` MODIFY COLUMN `mtime` '
            . 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        );
        $this->execute('ALTER TABLE `cars` MODIFY COLUMN `ctime` TIMESTAMP NULL');
        $this->execute('ALTER TABLE `cars` MODIFY COLUMN `owner_last_updated` DATETIME NULL');

        // --- 3. Rebuild the triggers --------------------------------------
        // Same post-20260902104755 bodies: rolling back the column *types*
        // does not roll back the verification columns themselves, which
        // 20260902104755 owns and its own down() removes.
        $this->createTriggers(
            'owner_last_updated, vericode_sent_at, email_bounced',
            'NEW.owner_last_updated, NEW.vericode_sent_at, NEW.email_bounced',
            'OLD.owner_last_updated, OLD.vericode_sent_at, OLD.email_bounced'
        );
        $this->assertCarsTriggersPresent();
    }

    /**
     * Refuses the conversion unless MySQL's clock and PHP's clock agree.
     *
     * `MODIFY COLUMN <ts> DATETIME` renders each stored value in the session's
     * `time_zone`, so the conversion is value-preserving only if that zone is
     * the one the application reads and writes dates in. Comparing
     * `@@session.time_zone` against `@@global.time_zone` does **not** establish
     * that — both read `SYSTEM` on a machine where MySQL follows the OS while
     * PHP has fallen back to UTC because `date.timezone` is unset, leaving the
     * two seven hours apart while the comparison passes. Comparing the two
     * clocks directly is the only check that catches it.
     *
     * A migration that throws is not recorded in `phinxlog`, so this is safely
     * re-runnable once `date.timezone` is aligned with the MySQL server zone.
     *
     * @throws \RuntimeException If the two clocks differ by more than
     *                           {@see CLOCK_SKEW_TOLERANCE_SECONDS}.
     */
    private function assertClockAlignment(): void
    {
        $row = $this->fetchRow('SELECT NOW() AS db_now');
        $dbNow = is_array($row) ? (string)($row['db_now'] ?? '') : '';

        self::assertClocksAligned($dbNow, date('Y-m-d H:i:s'));
    }

    /**
     * The clock comparison itself, as a pure function of the two clock
     * readings, so it can be exercised directly by a test without standing up a
     * Phinx adapter. {@see assertClockAlignment()} supplies the live readings.
     *
     * @param string $dbNow  MySQL's NOW(), as read from the migration connection
     * @param string $phpNow PHP's current time in the same 'Y-m-d H:i:s' format
     * @throws \RuntimeException If MySQL's clock is unreadable, or the two
     *                           clocks differ by more than
     *                           {@see CLOCK_SKEW_TOLERANCE_SECONDS}.
     */
    public static function assertClocksAligned(string $dbNow, string $phpNow): void
    {
        $dbTime = strtotime($dbNow);
        $phpTime = strtotime($phpNow);

        // Fail closed on either side. $phpNow is generated internally and
        // cannot realistically fail to parse, but this guard's whole purpose is
        // to abort an irreversible conversion, so neither operand is assumed.
        if ($phpTime === false) {
            throw new \RuntimeException(
                'Refusing to convert TIMESTAMP columns: could not parse PHP time '
                . '(got ' . var_export($phpNow, true) . '). The clock alignment check '
                . 'cannot be evaluated, and converting blind would risk shifting every value.'
            );
        }

        if ($dbTime === false) {
            throw new \RuntimeException(
                'Refusing to convert TIMESTAMP columns: could not read MySQL NOW() '
                . '(got ' . var_export($dbNow, true) . '). The clock alignment check '
                . 'cannot be evaluated, and converting blind would risk shifting every value.'
            );
        }

        $skew = abs($dbTime - $phpTime);

        if ($skew > self::CLOCK_SKEW_TOLERANCE_SECONDS) {
            throw new \RuntimeException(sprintf(
                'Refusing to convert TIMESTAMP columns: MySQL NOW() is %s but PHP now is %s '
                . '(%d seconds apart). Converting under a mismatched clock would shift every '
                . 'value permanently. Align date.timezone with the MySQL server timezone first.',
                $dbNow,
                $phpNow,
                $skew
            ));
        }
    }

    /**
     * Normalizes `cars_hist` dates carrying a zero day or zero month.
     *
     * Thirteen `purchasedate` and two `solddate` values read `1999-06-00`,
     * `2001-00-00` and similar — legacy rows from owners who knew the year, and
     * sometimes the month, but not the day. `cars` itself is clean; a prior
     * repair already promoted those zero components to `01` in the live table,
     * and these are the pre-repair audit snapshots that survived it.
     *
     * They must be repaired **before** the `cars_hist` ALTER. MySQL revalidates
     * the entire table during a rebuild, so columns this migration does not
     * convert still abort it under the production `sql_mode`
     * (`STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE`):
     *
     *     ERROR 1292 (22007): Incorrect date value: '1999-06-00'
     *                         for column 'purchasedate' at row 6475
     *
     * `sql_mode` is relaxed for the duration because the predicate itself needs
     * it: `DAY()`/`MONTH()` applied to a zero-component date is rejected under
     * the strict mode, so the rows could not even be selected. The prior value
     * is captured and restored rather than assumed. `cars_hist` has no UPDATE
     * trigger of its own, but `@disable_triggers` is set for consistency with
     * every other bulk write in this codebase.
     */
    private function normalizePartialHistoryDates(): void
    {
        $row = $this->fetchRow('SELECT @@session.sql_mode AS sql_mode');
        $originalSqlMode = is_array($row) ? (string)($row['sql_mode'] ?? '') : '';

        // Fail closed rather than relaxed. If the mode could not be read, the
        // finally below would "restore" sql_mode to '' — leaving every
        // subsequent statement in this migration (the cars_hist ALTER, the
        // backfill) running without STRICT_TRANS_TABLES/NO_ZERO_DATE, which is
        // exactly the state this method's finally exists to prevent.
        if ($originalSqlMode === '') {
            throw new \RuntimeException(
                'Refusing to relax sql_mode for the partial-date repair: could not read '
                . '@@session.sql_mode, so the original value cannot be restored afterwards. '
                . 'Every later statement in this migration would run unguarded.'
            );
        }

        $adapter = $this->getAdapter();
        $adapter->beginTransaction();
        $this->execute('SET @disable_triggers = 1');
        $this->execute("SET SESSION sql_mode = ''");

        try {
            foreach (['purchasedate', 'solddate'] as $column) {
                $this->execute(sprintf(
                    'UPDATE cars_hist'
                    . ' SET `%1$s` = MAKEDATE(YEAR(`%1$s`), 1)'
                    . ' + INTERVAL (GREATEST(MONTH(`%1$s`), 1) - 1) MONTH'
                    . ' WHERE DAY(`%1$s`) = 0 OR MONTH(`%1$s`) = 0',
                    $column
                ));
            }

            // Commit inside the try: the next statement in up() is a DDL
            // (ALTER TABLE cars_hist) which implicit-commits, so an open
            // transaction left by a throw here would be silently committed by
            // that ALTER rather than rolled back.
            $adapter->commitTransaction();
        } catch (\Throwable $e) {
            $adapter->rollbackTransaction();
            throw $e;
        } finally {
            // Restore the caller's sql_mode even if a repair throws — the
            // remaining statements in this migration must not run relaxed.
            $this->execute(sprintf("SET SESSION sql_mode = '%s'", $originalSqlMode));
            $this->execute('SET @disable_triggers = NULL');
        }
    }

    /**
     * Verifies all three `cars` audit triggers exist after (re)creation.
     *
     * A CREATE TRIGGER can silently fail to leave a trigger installed if the
     * migration user's privileges are borderline (e.g. TRIGGER grant present
     * but log_bin_trust_function_creators still off and SUPER absent on some
     * managed hosts) — {@see enableTrustFunctionCreators()} deliberately
     * continues rather than aborting in that case. This check turns a
     * silently-missing trigger into a loud migration failure instead of an
     * unaudited `cars` table discovered later.
     *
     * @throws \RuntimeException If any of cars_insert, cars_update, cars_delete
     *                           is missing after (re)creation.
     */
    private function assertCarsTriggersPresent(): void
    {
        $expected = ['cars_insert', 'cars_update', 'cars_delete'];

        $rows = $this->fetchAll(
            'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS '
            . "WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = 'cars'"
        );
        $present = array_column($rows, 'TRIGGER_NAME');
        $missing = array_diff($expected, $present);

        if ($missing !== []) {
            throw new \RuntimeException(
                'Trigger(s) not present after (re)creation on `cars`: ' . implode(', ', $missing) . '. '
                . 'These triggers were dropped and not recreated — writes to `cars` are currently '
                . 'UNAUDITED (no cars_hist rows will be written). Fix the migration user\'s privileges '
                . '(grant TRIGGER; if binary logging is on, also set log_bin_trust_function_creators=1 '
                . 'or grant SUPER/SYSTEM_VARIABLES_ADMIN) and re-run `composer migrate`.'
            );
        }
    }

    /**
     * Drops and recreates the three `cars` audit triggers.
     *
     * Copied from 20260902104755 (where it is private) so this migration does
     * not depend on another migration class staying loadable. The bodies are
     * the post-20260902104755 ones: pass the verification column list and the
     * matching NEW / OLD value lists.
     *
     * @param string $extraColumns   Trailing column names, or '' for none.
     * @param string $extraNewValues Matching NEW.* expressions, or ''.
     * @param string $extraOldValues Matching OLD.* expressions, or ''.
     */
    private function createTriggers(
        string $extraColumns,
        string $extraNewValues,
        string $extraOldValues
    ): void {
        $cols   = $extraColumns !== '' ? ', ' . $extraColumns : '';
        $newVal = $extraNewValues !== '' ? ', ' . $extraNewValues : '';
        $oldVal = $extraOldValues !== '' ? ', ' . $extraOldValues : '';

        $trustFunctionCreatorsSet = $this->enableTrustFunctionCreators();

        // A thrown CREATE TRIGGER must not leave log_bin_trust_function_creators
        // permanently relaxed server-wide — reset in finally so any exception
        // between enable and the end of trigger creation still restores it.
        try {
            $this->execute('DROP TRIGGER IF EXISTS `cars_insert`');
            $this->execute(
                "CREATE TRIGGER `cars_insert` AFTER INSERT ON `cars` FOR EACH ROW BEGIN
                     INSERT INTO cars_hist(
                         operation, car_id, ctime, mtime, model, series, variant,
                         year, type, chassis, chassis_override, color, engine, purchasedate, solddate, comments,
                         image, user_id, email, fname, lname, join_date, city, state, country,
                         lat, lon, website{$cols}
                     )
                     VALUES (
                         'INSERT', NEW.id, NEW.ctime, NEW.mtime, NEW.model,
                         NEW.series, NEW.variant, NEW.year, NEW.type, NEW.chassis, NEW.chassis_override,
                         NEW.color, NEW.engine, NEW.purchasedate, NEW.solddate, NEW.comments, NEW.image,
                         NEW.user_id, NEW.email, NEW.fname, NEW.lname, NEW.join_date, NEW.city,
                         NEW.state, NEW.country, NEW.lat, NEW.lon, NEW.website{$newVal}
                     );
                 END"
            );

            // The deliberate asymmetry — every value OLD.* except chassis_override,
            // which records NEW — is reproduced verbatim from 20260709000000.
            $this->execute('DROP TRIGGER IF EXISTS `cars_update`');
            $this->execute(
                "CREATE TRIGGER `cars_update` AFTER UPDATE ON `cars` FOR EACH ROW BEGIN
                     IF @disable_triggers IS NULL THEN
                         INSERT INTO cars_hist(
                             operation, car_id, ctime, mtime, model, series, variant,
                             year, type, chassis, chassis_override, color, engine, purchasedate, solddate, comments,
                             image, user_id, email, fname, lname, join_date, city, state, country,
                             lat, lon, website{$cols}
                         )
                         VALUES (
                             'UPDATE', OLD.id, OLD.ctime, OLD.mtime, OLD.model,
                             OLD.series, OLD.variant, OLD.year, OLD.type, OLD.chassis, NEW.chassis_override,
                             OLD.color, OLD.engine, OLD.purchasedate, OLD.solddate, OLD.comments, OLD.image,
                             OLD.user_id, OLD.email, OLD.fname, OLD.lname, OLD.join_date, OLD.city,
                             OLD.state, OLD.country, OLD.lat, OLD.lon, OLD.website{$oldVal}
                         );
                     END IF;
                 END"
            );

            $this->execute('DROP TRIGGER IF EXISTS `cars_delete`');
            $this->execute(
                "CREATE TRIGGER `cars_delete` AFTER DELETE ON `cars` FOR EACH ROW BEGIN
                     INSERT INTO cars_hist(
                         operation, car_id, ctime, mtime, model, series, variant,
                         year, type, chassis, chassis_override, color, engine, purchasedate, solddate, comments,
                         image, user_id, email, fname, lname, join_date, city, state, country,
                         lat, lon, website{$cols}
                     )
                     VALUES (
                         'DELETE', OLD.id, OLD.ctime, OLD.mtime, OLD.model,
                         OLD.series, OLD.variant, OLD.year, OLD.type, OLD.chassis, OLD.chassis_override,
                         OLD.color, OLD.engine, OLD.purchasedate, OLD.solddate, OLD.comments, OLD.image,
                         OLD.user_id, OLD.email, OLD.fname, OLD.lname, OLD.join_date, OLD.city,
                         OLD.state, OLD.country, OLD.lat, OLD.lon, OLD.website{$oldVal}
                     );
                 END"
            );
        } finally {
            $this->resetTrustFunctionCreators($trustFunctionCreatorsSet);
        }
    }

    /**
     * CREATE TRIGGER requires either SUPER privilege or log_bin_trust_function_creators=1
     * when binary logging is enabled. Attempt to set it globally; if the migration user
     * lacks SUPER/SYSTEM_VARIABLES_ADMIN, continue anyway — the variable may already be
     * set globally (common on managed hosting panels), otherwise the DBA must set it in
     * MySQL config (log_bin_trust_function_creators=1 in my.cnf).
     *
     * @return bool True if this call set the variable (and so should reset it afterward).
     */
    private function enableTrustFunctionCreators(): bool
    {
        try {
            $this->execute('SET GLOBAL log_bin_trust_function_creators = 1');
            return true;
        } catch (\RuntimeException $e) {
            if (isset($this->output)) {
                $this->output->writeln(
                    '<comment>Warning: Could not SET GLOBAL log_bin_trust_function_creators=1 '
                    . '— continuing. If CREATE TRIGGER fails below, set this variable in my.cnf.</comment>'
                );
            }
            return false;
        }
    }

    /**
     * Resets log_bin_trust_function_creators if this migration run set it — limits the
     * window of elevated trust to only the trigger creation steps.
     *
     * @throws \RuntimeException If the reset fails after we had relaxed the flag — this
     *                           leaves it relaxed server-wide, which must fail the deploy
     *                           visibly rather than warn and continue.
     */
    private function resetTrustFunctionCreators(bool $wasSet): void
    {
        if (!$wasSet) {
            return;
        }
        try {
            $this->execute('SET GLOBAL log_bin_trust_function_creators = 0');
        } catch (\RuntimeException $e) {
            if (isset($this->output)) {
                $this->output->writeln(
                    '<error>Could not reset log_bin_trust_function_creators=0: '
                    . $e->getMessage()
                    . ' — log_bin_trust_function_creators is left relaxed (=1) server-wide. '
                    . 'Reset it manually in MySQL or my.cnf.</error>'
                );
            }
            throw $e;
        }
    }
}
