<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Drops four legacy `users` columns confirmed unused by ElanRegistry and
 * unread by UserSpice (#1669): `twoKey`, `twoEnabled`, `twoDate` (a defunct
 * two-factor-auth scaffold added by a stock UserSpice update component that
 * no longer exists in this tree, but nothing in `users/` or `usersc/` reads
 * any of the three), and `org` (no column-style reference anywhere in
 * `app/`, `usersc/`, or `users/`).
 *
 * 20260709000000_add_elanregistry_baseline.php was fixed by #1672 to never
 * ADD these columns, so a freshly provisioned environment never has them.
 * This migration only has work to do on an environment where an older
 * baseline run (or the original UserSpice update component) already added
 * them — each drop is guarded by `hasColumn()` so the migration is a no-op
 * everywhere else.
 *
 * up()/down() are used instead of change() because a guarded removeColumn()
 * is not safely auto-reversible: Phinx's change() rollback works by replaying
 * the method body to record which DDL calls it would make, but by rollback
 * time the columns are already gone, so hasColumn() returns false for all
 * four and nothing gets recorded — migrate:rollback would report success
 * while restoring nothing. down() re-adds the columns unconditionally
 * instead. Only the column structure is restored, not the original values
 * (matching the documented caveat on `ModifiedBy` in
 * 20260710120000_change_cars_year_and_drop_modifiedby.php) — column defs
 * below taken from the current dev database (SHOW FULL COLUMNS FROM users).
 */
final class DropUsersLegacyColumns extends AbstractMigration
{
    public function up(): void
    {
        foreach (['twoKey', 'twoEnabled', 'twoDate', 'org'] as $column) {
            if ($this->table('users')->hasColumn($column)) {
                $this->table('users')->removeColumn($column)->update();
            }
        }
    }

    public function down(): void
    {
        $users = $this->table('users');

        if (!$users->hasColumn('twoKey')) {
            $users->addColumn('twoKey', 'string', ['limit' => 16, 'null' => true, 'default' => null]);
        }
        if (!$users->hasColumn('twoEnabled')) {
            $users->addColumn('twoEnabled', 'integer', ['null' => true, 'default' => 0]);
        }
        if (!$users->hasColumn('twoDate')) {
            $users->addColumn('twoDate', 'datetime', ['null' => true, 'default' => null]);
        }
        if (!$users->hasColumn('org')) {
            $users->addColumn('org', 'integer', ['null' => true, 'default' => null]);
        }

        $users->update();
    }
}
