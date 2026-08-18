<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Issue #1679: apply ElanRegistry's real settings defaults to the `settings`
 * row 1 values that were never actually applied on real installs.
 *
 * This migration is the sole place ElanRegistry's settings defaults are
 * written on any install. That includes every value the application depends
 * on (e.g. `elan_image_max`, which controls the FilePond upload widget's
 * file-count limit), not just the values corrected by #1679.
 *
 * Row 1 is created here when it does not already exist. On a wizard install
 * UserSpice creates it before this repo's provisioning runs, so the INSERT is
 * skipped and only the UPDATE applies. On a script-provisioned schema
 * (`scripts/provision-schema.sh`) the wizard never runs and the vendored
 * structure dump is DDL-only, so without the INSERT the UPDATE would match
 * zero rows and every consumer of `settings` would then misbehave —
 * `Car::__construct()` among them. `SettingsBaselineSeed` used to cover that
 * case; #1679 removed it in favour of this migration, which must therefore
 * handle both shapes.
 *
 * The INSERT supplies only the columns that are NOT NULL without a default,
 * because MySQL rejects the row otherwise; the UPDATE immediately below sets
 * the values that actually matter. The `elan_*_cdn` columns are deliberately
 * left empty rather than seeded with the dev database's values: they hold
 * Bootstrap 4-era markup superseded by ADR-015's bundled assets, no
 * application code reads them any more, and copying them here would enshrine
 * dead configuration as the official baseline for new installs.
 *
 * MAINTENANCE: that INSERT column list is hardcoded, unlike the removed
 * `SettingsBaselineSeed`, which discovered columns from `information_schema`
 * at run time. The trade is deliberate — a hardcoded list fails loudly on a
 * schema it no longer matches, where dynamic discovery would silently write a
 * misconfigured row — but it does mean a vendored-schema bump that adds a
 * NOT NULL column with no default breaks provisioning with a MySQL error
 * naming that column. The fix is to add the column and its default here; the
 * error is the intended signal, not a regression.
 *
 * down() is intentionally a no-op — see database/migrations/README.md's
 * documented carve-out for fixing a bad default that should not be restored
 * on rollback.
 */
final class UpdateSettingsBaselineDefaults extends AbstractMigration
{
    public function up(): void
    {
        $adapter = $this->getAdapter();
        $adapter->beginTransaction();

        if ($this->fetchRow('SELECT id FROM `settings` WHERE id = 1') === false) {
            $this->execute(
                "INSERT INTO `settings`
                    (id, force_ssl, css_sample, us_css1, us_css2, us_css3, site_name,
                     site_offline, force_pr, fblogin, req_cap, req_num, min_pw, max_pw,
                     min_un, max_un, messaging, snooping, echouser, wys, change_un,
                     msg_notification, permission_restriction, auto_assign_un,
                     page_permission_restriction, msg_blocked_users, msg_default_to,
                     notifications, notif_daylimit, page_default_private, navigation_type,
                     copyright, custom_settings, system_announcement, join_vericode_expiry,
                     reset_vericode_expiry, admin_verify, admin_verify_timeout,
                     session_manager, elan_backup_age, elan_image_dir, elan_image_max,
                     elan_jquery_cdn, elan_bootstrap_js_cdn, elan_bootstrap_css_cdn,
                     elan_popper_cdn, elan_fontawesome_cdn, elan_bootswatch_cdn,
                     elan_datatables_js_cdn, elan_datatables_css_cdn, elan_datepicker_js_cdn,
                     elan_datepicker_css_cdn, elan_jquery_ui_cdn, elan_dropzone_js_cdn,
                     elan_dropzone_css_cdn)
                 VALUES
                    (1, 0, 0, '../users/css/color_schemes/muted.css', '../users/css/datatables.css',
                     '../usersc/css/custom.css', 'Lotus Elan Registry', 0, 0, 0, 1, 1, 5, 32,
                     5, 30, 0, 1, 0, 1, 0, 0, 1, 0, 1, 0, 7, 1, 1, 0, 0,
                     'Lotus Elan Registry and UniBrain', 1, '', 24, 120, 1, 120, 1, 1,
                     'userimages/', 6,
                     '', '', '', '', '', '', '', '', '', '', '', '', '')"
            );

            $present = $this->fetchRow('SELECT COUNT(*) AS c FROM `settings` WHERE id = 1');
            if ($present === false || (int) $present['c'] !== 1) {
                $adapter->rollbackTransaction();
                throw new RuntimeException(
                    'UpdateSettingsBaselineDefaults: the INSERT ran but settings row id = 1 does not ' .
                    'exist. Car::__construct() and other framework code silently misbehave without ' .
                    'it — investigate before continuing.'
                );
            }
        }

        $this->execute(
            "UPDATE `settings` SET
                `site_name` = 'Lotus Elan Registry',
                `template` = 'customizer',
                `copyright` = 'Lotus Elan Registry and UniBrain',
                `navigation_type` = 0,
                `elan_image_dir` = 'userimages/',
                `elan_image_max` = 6,
                `permission_restriction` = 1,
                `session_manager` = 1,
                `recaptcha` = 0,
                `req_cap` = 1,
                `req_num` = 1,
                `email_login` = 2,
                `min_pw` = 5,
                `max_pw` = 32,
                `min_un` = 5,
                `max_un` = 30,
                `pwl_length` = 5,
                `redirect_uri_after_login` = 'users/account.php',
                `registration` = 1,
                `join_vericode_expiry` = 24,
                `change_un` = 0,
                `reset_vericode_expiry` = 120,
                `err_time` = 20,
                `container_open_class` = 'container-fluid'
             WHERE `id` = 1"
        );
        $adapter->commitTransaction();
    }

    public function down(): void
    {
        // Intentionally a no-op. This migration fixes bad defaults that were
        // never intentionally set on real installs (see class docblock);
        // restoring err_time to 15 or leaving the other keys unset on
        // rollback isn't useful. See database/migrations/README.md.
    }
}
