<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Issue #1155: schema foundation for the car verification system.
 *
 * Adds three columns to `cars` (and their mirrors on `cars_hist`):
 *
 * - `owner_last_updated` — when the owner last confirmed or edited the record.
 * - `vericode_sent_at`   — when the most recent verification email was sent.
 * - `email_bounced`      — flag set when that email hard-bounced.
 *
 * up() + down() are used instead of change() because this migration mixes
 * reversible DDL with a one-time data backfill and with trigger rebuilds, and
 * Phinx can auto-reverse none of the latter two. See
 * database/migrations/README.md — "Only fall back to explicit up() + down()".
 *
 * NOT ATOMIC: MySQL issues an implicit commit on every DDL statement, so the
 * ALTER TABLE and CREATE TRIGGER steps below cannot be wrapped in a
 * transaction. Only the backfill UPDATE is transactional. Every step is
 * guarded (hasColumn(), DROP TRIGGER IF EXISTS, and an `IS NULL` predicate on
 * the backfill), so re-running the migration after a partial failure is safe
 * and does no duplicate work.
 */
final class AddCarVerificationColumns extends AbstractMigration
{
    public function up(): void
    {
        // --- 1. New columns on `cars` -------------------------------------
        $cars = $this->table('cars');

        if (!$cars->hasColumn('owner_last_updated')) {
            $cars->addColumn('owner_last_updated', 'datetime', [
                'null'  => true,
                'after' => 'last_verified',
            ])->update();
        }
        if (!$cars->hasColumn('vericode_sent_at')) {
            $cars->addColumn('vericode_sent_at', 'datetime', ['null' => true])->update();
        }
        if (!$cars->hasColumn('email_bounced')) {
            $cars->addColumn('email_bounced', 'boolean', [
                'null'    => false,
                'default' => 0,
            ])->update();
        }

        // --- 2. Backfill owner_last_updated from mtime --------------------
        // `mtime` is TIMESTAMP and `owner_last_updated` is DATETIME; the implicit
        // conversion is intentional and matches the type specified by issue #1155.
        // The IS NULL predicate keeps this idempotent across re-runs.
        //
        // `mtime = mtime` is load-bearing, not a no-op: cars.mtime is declared
        // ON UPDATE CURRENT_TIMESTAMP, so any UPDATE touching a row silently
        // rewrites mtime to the migration's run time and destroys every real
        // modification timestamp in the table. Assigning the column explicitly
        // suppresses the ON UPDATE clause and preserves the existing value.
        //
        // @disable_triggers suppresses cars_update for this UPDATE: at this point
        // in up() the pre-migration trigger is still installed (rebuilt in step 4
        // below), and without suppression it would fire once per row, flooding
        // cars_hist with a bogus 'UPDATE' entry for internal bookkeeping that
        // isn't a real edit — the same project-wide escape hatch used by bulk
        // maintenance scripts (see 20260709000000's docblock).
        $adapter = $this->getAdapter();
        $adapter->beginTransaction();
        $this->execute('SET @disable_triggers = 1');
        $this->execute(
            'UPDATE cars SET owner_last_updated = mtime, mtime = mtime WHERE owner_last_updated IS NULL'
        );
        $this->execute('SET @disable_triggers = NULL');
        $adapter->commitTransaction();

        // --- 3. Mirror the columns onto `cars_hist` -----------------------
        $hist = $this->table('cars_hist');

        if (!$hist->hasColumn('owner_last_updated')) {
            $hist->addColumn('owner_last_updated', 'datetime', ['null' => true])->update();
        }
        if (!$hist->hasColumn('vericode_sent_at')) {
            $hist->addColumn('vericode_sent_at', 'datetime', ['null' => true])->update();
        }
        if (!$hist->hasColumn('email_bounced')) {
            $hist->addColumn('email_bounced', 'boolean', [
                'null'    => false,
                'default' => 0,
            ])->update();
        }

        // --- 4. Rebuild the audit triggers with the new columns -----------
        // MySQL has no CREATE TRIGGER IF NOT EXISTS and no partial ALTER for a
        // trigger body, so each trigger is dropped and recreated in full. The
        // bodies below are the ones from 20260709000000 with the three new
        // columns appended to the column list and VALUES tuple; the deliberate
        // OLD/NEW asymmetry on chassis_override in cars_update is preserved.
        $this->createTriggers(
            "owner_last_updated, vericode_sent_at, email_bounced",
            "NEW.owner_last_updated, NEW.vericode_sent_at, NEW.email_bounced",
            "OLD.owner_last_updated, OLD.vericode_sent_at, OLD.email_bounced"
        );
    }

    public function down(): void
    {
        // --- 1. Drop the cars_hist columns --------------------------------
        $hist = $this->table('cars_hist');
        foreach (['owner_last_updated', 'vericode_sent_at', 'email_bounced'] as $column) {
            if ($hist->hasColumn($column)) {
                $hist->removeColumn($column)->update();
            }
        }

        // --- 2. Restore the pre-migration trigger bodies ------------------
        // Must happen before the `cars` columns are dropped: MySQL refuses to
        // DROP a column that a trigger body still references.
        $this->createTriggers('', '', '');

        // --- 3. Drop the cars columns -------------------------------------
        $cars = $this->table('cars');
        foreach (['owner_last_updated', 'vericode_sent_at', 'email_bounced'] as $column) {
            if ($cars->hasColumn($column)) {
                $cars->removeColumn($column)->update();
            }
        }
    }

    /**
     * Drops and recreates the three `cars` audit triggers.
     *
     * The three arguments carry the verification columns: pass the column list
     * and the matching NEW / OLD value lists to create the post-migration
     * bodies, or empty strings to restore the original pre-migration bodies.
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
     */
    private function resetTrustFunctionCreators(bool $wasSet): void
    {
        if (!$wasSet) {
            return;
        }
        try {
            $this->execute('SET GLOBAL log_bin_trust_function_creators = 0');
        } catch (\Exception $e) {
            if (isset($this->output)) {
                $this->output->writeln(
                    '<comment>Warning: Could not reset log_bin_trust_function_creators=0: '
                    . $e->getMessage()
                    . ' — set it manually in MySQL or my.cnf.</comment>'
                );
            }
        }
    }
}
