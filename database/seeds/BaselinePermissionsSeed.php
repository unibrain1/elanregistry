<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Insert the `permissions` row (id = 3, name "Editor", descrip "Elanregistry
 * Editor") that ElanRegistry's second privileged role depends on.
 *
 * Stock UserSpice 6.1.4 ships only `1 = User` and `2 = Administrator` in
 * `permissions` — "Editor" is an ElanRegistry-specific tier layered on top,
 * and dev/prod acquired this row by hand at some point outside any tracked
 * migration or seed. A fresh install never gets it, even though
 * `PagePermissionClassifier::PERM_EDITOR`,
 * `app/admin/scripts/maintenance/21-Fix-Page-Permissions.php`, and
 * `PageRegistrationSeed` all hardcode `permission_id = 3` assuming this row
 * exists. `id` is forced to 3 (rather than left to auto-increment) to match
 * that hardcoded assumption exactly. This is the inverse of `NoownerSeed`,
 * which deliberately lets its row's id fall to AUTO_INCREMENT and documents
 * the resulting id-83 fragility as an open gap (#1562) — unlike `noowner`,
 * every consumer of `permissions.id = 3` looks it up by the literal id, not
 * by name, so leaving it to AUTO_INCREMENT would reproduce the same fragility
 * here instead of avoiding it.
 *
 * Must run before `PageRegistrationSeed`: pages classified as
 * "Admin+Editor" insert a `permission_page_matches` row referencing
 * `permission_id = 3`, which would violate referential expectations (no FK
 * enforces it, but the row would be orphaned/meaningless) if this seed
 * hadn't already created it. `provision-schema.sh` runs seeds via `-s
 * <ClassName>` per file (bypassing Phinx's `getDependencies()` ordering —
 * see that script's comment), enumerated by globbing `database/seeds/*.php`
 * in filesystem order, which is alphabetical. This class is named
 * `BaselinePermissionsSeed` rather than `PermissionsBaselineSeed`
 * specifically so it sorts ahead of `PageRegistrationSeed` — the ordering
 * dependency is real even though seeds are otherwise independent.
 *
 * Idempotent: does nothing if row id = 3 already exists.
 */
final class BaselinePermissionsSeed extends AbstractSeed
{
    public function run(): void
    {
        if ($this->fetchRow('SELECT id FROM `permissions` WHERE id = 3') !== false) {
            return;
        }

        $inserted = $this->execute(
            'INSERT INTO `permissions` (id, name, descrip) VALUES (3, ?, ?)',
            ['Editor', 'Elanregistry Editor']
        );

        if ($inserted !== 1) {
            throw new RuntimeException(
                "BaselinePermissionsSeed: the INSERT reported {$inserted} affected rows, expected 1. " .
                'Pages classified as Admin+Editor cannot be registered correctly without ' .
                '`permissions` row id = 3 — investigate before continuing.'
            );
        }
    }
}
