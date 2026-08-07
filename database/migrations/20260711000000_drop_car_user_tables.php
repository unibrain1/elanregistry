<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropCarUserTables extends AbstractMigration
{
    // car_user recorded car ownership as (userid, car_id) rows, duplicating the
    // ownership already authoritative on cars.user_id. It had no FK constraint
    // back to either table, so the two representations could drift — a row in
    // car_user could point at a car whose cars.user_id disagreed, producing
    // inconsistent owner data in reports and queries. cars.user_id is the sole
    // ownership relationship; this migration drops car_user / car_user_hist and
    // their audit triggers, after reconciling and verifying any drift below.
    //
    // up()   — drop the audit triggers, then car_user_hist and car_user
    // down() — recreate the tables and triggers from the original schema DDL for
    //          rollback safety
    //
    // up() + down() are used instead of change() because DROP TABLE / DROP
    // TRIGGER is not auto-reversible.
    public function up(): void
    {
        // A freshly provisioned environment (database/vendor/ base +
        // 20260709000000_add_elanregistry_baseline) never creates car_user in the
        // first place — the baseline reproduces dev, which already lacks it. This
        // migration only has work to do on an environment that still has the table.
        $exists = $this->fetchAll(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'car_user'"
        );
        if ((int) ($exists[0]['cnt'] ?? 0) === 0) {
            return;
        }

        // All reads (fetchRow / fetchAll) run before any DDL. DDL triggers an
        // implicit commit in MySQL, so any exception thrown after DDL starts
        // would leave the schema partially changed. By doing all data work first,
        // a failure leaves both the data and the schema untouched.

        // ── Step 1: Guard against unrecoverable orphans ───────────────────────
        //
        // Block first, before any data is touched, if any car_user row references
        // a car_id that no longer exists in cars. The reconciliation JOINs in
        // Step 2 only ever touch rows with a matching car, so a row whose car was
        // hard-deleted (CarRepository::deleteCar() removes from cars with no
        // car_user cleanup) is invisible to them and would otherwise be destroyed
        // unnoticed by the DROP below.
        $noCar = $this->fetchRow(
            "SELECT COUNT(*) AS n FROM car_user cu
             LEFT JOIN cars c ON cu.car_id = c.id
             WHERE c.id IS NULL"
        );
        if ((int) ($noCar['n'] ?? 0) > 0) {
            throw new \RuntimeException(
                "Cannot drop car_user: {$noCar['n']} row(s) reference a car_id that no " .
                'longer exists in cars. Investigate before proceeding — dropping would ' .
                'destroy the last record of these relationships.'
            );
        }

        // ── Step 2: Reconcile drift ────────────────────────────────────────────
        //
        // car_user had no FK constraint, so cars.user_id and car_user.userid
        // could diverge whenever an ownership transfer updated one but not the
        // other. Prod analysis (2026-07-11) found two drifted rows:
        //
        //   car 213  (4399)        — car_user pointed at a duplicate account
        //                            (sbarnes/3994); cars.user_id correctly
        //                            points at the primary account (stevebarnes/473)
        //   car 1432 (7312190046L) — user was deleted and car was reassigned to
        //                            noowner in cars.user_id, but car_user still
        //                            pointed at the deleted user (pwelsh/2918)
        //
        // cars.user_id is authoritative; car_user is the stale mirror. Fix drift
        // here so the migration is self-contained — no manual pre-flight script.

        // Update car_user rows where userid disagrees with cars.user_id.
        $this->execute(
            "UPDATE car_user cu
             JOIN cars c ON cu.car_id = c.id
             SET cu.userid = c.user_id
             WHERE c.user_id IS NOT NULL
               AND c.user_id != cu.userid"
        );

        // Remove car_user rows for cars that have no owner (cars.user_id IS NULL).
        // A NULL owner has no valid userid to store in the junction table.
        $this->execute(
            "DELETE cu FROM car_user cu
             JOIN cars c ON cu.car_id = c.id
             WHERE c.user_id IS NULL"
        );

        // ── Step 3: Guard — hard blocks that cannot be auto-fixed ────────────
        //
        // These checks catch data integrity problems that require human review.
        // Unlike drift (which cars.user_id can authoritatively resolve), an
        // orphaned user_id means the owning user no longer exists — dropping
        // car_user with orphaned rows would lose the last known ownership record.

        // Block if any car references a user that no longer exists.
        $orphaned = $this->fetchRow(
            "SELECT COUNT(*) AS n FROM cars
             WHERE user_id IS NOT NULL
               AND user_id NOT IN (SELECT id FROM users)"
        );
        if ((int) ($orphaned['n'] ?? 0) > 0) {
            throw new \RuntimeException(
                "Cannot drop car_user: {$orphaned['n']} car(s) have user_id referencing " .
                "a non-existent user. Reassign those cars before running this migration."
            );
        }

        // Verify the reconciliation above eliminated all drift. This should never
        // fire in practice, but catches any edge case the UPDATE missed.
        $drifted = $this->fetchRow(
            "SELECT COUNT(*) AS n
             FROM car_user cu
             JOIN cars c ON cu.car_id = c.id
             WHERE c.user_id IS NULL OR c.user_id != cu.userid"
        );
        if ((int) ($drifted['n'] ?? 0) > 0) {
            throw new \RuntimeException(
                "Cannot drop car_user: {$drifted['n']} drifted row(s) remain after " .
                "reconciliation. Investigate before proceeding."
            );
        }

        // ── Step 4: Drop ─────────────────────────────────────────────────────

        // Triggers must be dropped before their table.
        $this->execute("DROP TRIGGER IF EXISTS `car_user_delete`");
        $this->execute("DROP TRIGGER IF EXISTS `car_user_update`");
        $this->execute("DROP TRIGGER IF EXISTS `car_user_insert`");

        // Drop the history table before the main table.
        $this->execute("DROP TABLE IF EXISTS `car_user_hist`");
        $this->execute("DROP TABLE IF EXISTS `car_user`");
    }

    public function down(): void
    {
        // NOTE: down() recreates car_user and car_user_hist as empty tables — it is a
        // structural-only rollback. Any application code that reads from car_user will
        // see no cars for any owner until the data is restored. After reverting the
        // application code, run this backfill to restore data parity:
        //
        //   INSERT INTO car_user (userid, car_id)
        //     SELECT user_id, id FROM cars WHERE user_id IS NOT NULL;
        //
        // Never run this rollback on a live system without also reverting the
        // application code — car ownership queries will silently return empty sets.

        // Recreate car_user if absent.
        $result = $this->fetchAll(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'car_user'"
        );
        if ((int) ($result[0]['cnt'] ?? 0) === 0) {
            $this->execute(
                "CREATE TABLE `car_user` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `userid` int(11) NOT NULL,
                  `car_id` int(11) NOT NULL,
                  `mtime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `idx_car_user_car_id` (`car_id`),
                  KEY `idx_car_user_userid` (`userid`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // Recreate car_user_hist if absent.
        $result = $this->fetchAll(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'car_user_hist'"
        );
        if ((int) ($result[0]['cnt'] ?? 0) === 0) {
            $this->execute(
                "CREATE TABLE `car_user_hist` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `operation` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
                  `car_id` int(11) UNSIGNED NOT NULL,
                  `userid` int(11) DEFAULT NULL,
                  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `idx_car_user_hist_car_id` (`car_id`),
                  KEY `idx_car_user_hist_userid` (`userid`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // MySQL has no CREATE TRIGGER IF NOT EXISTS — drop-then-recreate is the
        // idempotent pattern.
        $this->execute("DROP TRIGGER IF EXISTS `car_user_insert`");
        $this->execute(
            "CREATE TRIGGER `car_user_insert` AFTER INSERT ON `car_user` FOR EACH ROW BEGIN
                INSERT INTO car_user_hist (operation, car_id, userid)
                VALUES ('INSERT', NEW.car_id, NEW.userid);
            END"
        );

        $this->execute("DROP TRIGGER IF EXISTS `car_user_update`");
        $this->execute(
            "CREATE TRIGGER `car_user_update` AFTER UPDATE ON `car_user` FOR EACH ROW BEGIN
                IF @disable_triggers IS NULL THEN
                    INSERT INTO car_user_hist (operation, car_id, userid)
                    VALUES ('UPDATE', OLD.car_id, OLD.userid);
                END IF;
            END"
        );

        $this->execute("DROP TRIGGER IF EXISTS `car_user_delete`");
        $this->execute(
            "CREATE TRIGGER `car_user_delete` AFTER DELETE ON `car_user` FOR EACH ROW BEGIN
                INSERT INTO car_user_hist (operation, car_id, userid)
                VALUES ('DELETE', OLD.car_id, OLD.userid);
            END"
        );
    }
}
