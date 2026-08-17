<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Drops four legacy `users` columns confirmed unused by ElanRegistry and
 * unread by UserSpice (#1669): `twoKey`, `twoEnabled`, `twoDate` (a defunct
 * two-factor-auth scaffold — `users/updates/components/4A6BdJHyvP4a.php` adds
 * `twoDate` but nothing in `users/` or `usersc/` reads any of the three), and
 * `org` (no column-style reference anywhere in `app/`, `usersc/`, or `users/`).
 *
 * 20260709000000_add_elanregistry_baseline.php was fixed by #1672 to never
 * ADD these columns, so a freshly provisioned environment never has them.
 * This migration only has work to do on an environment where an older
 * baseline run (or the original UserSpice update component) already added
 * them — each drop is guarded by `hasColumn()` so the migration is a no-op
 * everywhere else.
 *
 * Reversible: Phinx auto-generates the inverse `addColumn()` calls on
 * rollback, but only the column structure is restored — the original values
 * are not recoverable, matching the documented caveat on `ModifiedBy` in
 * 20260710120000_change_cars_year_and_drop_modifiedby.php.
 */
final class DropUsersLegacyColumns extends AbstractMigration
{
    public function change(): void
    {
        foreach (['twoKey', 'twoEnabled', 'twoDate', 'org'] as $column) {
            if ($this->table('users')->hasColumn($column)) {
                $this->table('users')->removeColumn($column)->update();
            }
        }
    }
}
