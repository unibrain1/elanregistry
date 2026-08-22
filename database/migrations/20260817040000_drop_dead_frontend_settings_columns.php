<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Drops three dead `settings` columns confirmed unused by ElanRegistry (#1725):
 * `elan_jquery_ui_cdn`, `elan_dropzone_js_cdn`, `elan_dropzone_css_cdn`.
 *
 * These were added by 20260709000000_add_elanregistry_baseline.php (lines
 * 628-630, as `mediumtext NOT NULL`) and given an empty-string placeholder
 * (never populated with real values — see that migration's own note on why
 * the `elan_*_cdn` columns are left blank) by
 * 20260817033111_update_settings_baseline_defaults.php (lines 69-70), but
 * jQuery UI and Dropzone were never actually vendored or wired up anywhere
 * in the application — no vendored files for either exist in this repo or
 * in a fresh UserSpice 6.1.4 install, and no application code reads these
 * columns (`grep -rn "elan_jquery_ui_cdn\|elan_dropzone_js_cdn\|elan_dropzone_css_cdn"
 * app usersc users --include="*.php" | grep -v database/migrations` returns
 * nothing).
 *
 * up()/down() are used instead of change() because a guarded removeColumn()
 * is not safely auto-reversible: Phinx's change() rollback works by replaying
 * the method body to record which DDL calls it would make, but by rollback
 * time the columns are already gone, so hasColumn() returns false for all
 * three and nothing gets recorded — migrate:rollback would report success
 * while restoring nothing. down() re-adds the columns unconditionally
 * instead, as nullable (no NOT NULL constraint) so rollback can never fail
 * against a populated `settings` row for lack of a value to backfill — only
 * the column structure is restored, not the original values (matching the
 * documented caveat on `ModifiedBy` in
 * 20260710120000_change_cars_year_and_drop_modifiedby.php, also restated in
 * 20260817011351_drop_users_legacy_columns.php).
 * up() guards each drop with hasColumn() in case this migration is re-run in
 * test setups or an environment ran an older baseline without these columns.
 */
final class DropDeadFrontendSettingsColumns extends AbstractMigration
{
    private const COLUMNS = ['elan_jquery_ui_cdn', 'elan_dropzone_js_cdn', 'elan_dropzone_css_cdn'];

    public function up(): void
    {
        foreach (self::COLUMNS as $column) {
            if ($this->table('settings')->hasColumn($column)) {
                $this->table('settings')->removeColumn($column)->update();
            }
        }
    }

    public function down(): void
    {
        $settings = $this->table('settings');

        foreach (self::COLUMNS as $column) {
            if (!$settings->hasColumn($column)) {
                $settings->addColumn($column, 'text', ['limit' => MysqlAdapter::TEXT_MEDIUM, 'null' => true]);
            }
        }

        $settings->update();
    }
}
