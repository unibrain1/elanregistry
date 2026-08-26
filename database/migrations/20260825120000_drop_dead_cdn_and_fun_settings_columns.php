<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

/**
 * Drops the remaining dead `settings` columns from the pre-ADR-015
 * database-driven CDN config model, plus the unused `fun` column (#1734):
 * `elan_jquery_cdn`, `elan_bootstrap_js_cdn`, `elan_bootstrap_css_cdn`,
 * `elan_popper_cdn`, `elan_fontawesome_cdn`, `elan_bootswatch_cdn`,
 * `elan_datatables_js_cdn`, `elan_datatables_css_cdn`,
 * `elan_datepicker_js_cdn`, `elan_datepicker_css_cdn`, `elan_chartjs_cdn`,
 * `fun`.
 *
 * These were added by 20260709000000_add_elanregistry_baseline.php (lines
 * 618-631, 637) for the CDN-config model ADR-015 (self-hosting frontend
 * libraries) superseded. Three sibling columns from this same family
 * (`elan_jquery_ui_cdn`, `elan_dropzone_js_cdn`, `elan_dropzone_css_cdn`)
 * were already dropped in #1725 (20260817040000_drop_dead_frontend_settings_columns.php).
 * No application code reads any of these 12 columns — re-verified fresh at
 * implementation time (grep -rn "elan_jquery_cdn\|elan_bootstrap_js_cdn\|
 * elan_bootstrap_css_cdn\|elan_popper_cdn\|elan_fontawesome_cdn\|
 * elan_bootswatch_cdn\|elan_datatables_js_cdn\|elan_datatables_css_cdn\|
 * elan_datepicker_js_cdn\|elan_datepicker_css_cdn\|elan_chartjs_cdn\|fun"
 * app usersc users --include="*.php" | grep -v database/migrations returns
 * nothing referencing the `settings.fun` column or any of the CDN columns).
 *
 * up()/down() are used instead of change() for the same reason as #1725: a
 * guarded removeColumn() is not safely auto-reversible, since hasColumn()
 * returns false for all of these by rollback time and change()'s replay
 * would record nothing to restore. down() re-adds the columns
 * unconditionally instead, as nullable text (no NOT NULL constraint) so
 * rollback can never fail against a populated `settings` row for lack of a
 * value to backfill — only column structure is restored, not original
 * values or the `elan_chartjs_cdn` column comment (matching the documented
 * precedent in 20260817040000_drop_dead_frontend_settings_columns.php).
 * up() guards each drop with hasColumn() in case this migration is re-run in
 * test setups or an environment ran an older baseline without these columns.
 */
final class DropDeadCdnAndFunSettingsColumns extends AbstractMigration
{
    private const COLUMNS = [
        'elan_jquery_cdn',
        'elan_bootstrap_js_cdn',
        'elan_bootstrap_css_cdn',
        'elan_popper_cdn',
        'elan_fontawesome_cdn',
        'elan_bootswatch_cdn',
        'elan_datatables_js_cdn',
        'elan_datatables_css_cdn',
        'elan_datepicker_js_cdn',
        'elan_datepicker_css_cdn',
        'elan_chartjs_cdn',
        'fun',
    ];

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
