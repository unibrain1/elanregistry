<?php

declare(strict_types=1);

require_once __DIR__ . '/../IntegrationTestCase.php';

use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for migration 20260817033111_update_settings_baseline_defaults
 *
 * Verifies the migration writes ElanRegistry's real defaults onto the
 * `settings` row UserSpice's install wizard creates (row id=1) — the row
 * this migration is the sole source of truth for on a real install (issue
 * #1679). Covers all 23 columns the migration sets, not just the 9 the
 * issue originally called out.
 */
#[Group('integration')]
#[Group('migration')]
final class UpdateSettingsBaselineDefaultsMigrationTest extends IntegrationTestCase
{
    /** Column => expected value, mirrors the migration's UPDATE statement exactly. */
    private const EXPECTED = [
        'site_name' => 'Lotus Elan Registry',
        'template' => 'customizer',
        'copyright' => 'Lotus Elan Registry and UniBrain',
        'navigation_type' => 0,
        'elan_image_dir' => 'userimages/',
        'elan_image_max' => 6,
        'permission_restriction' => 1,
        'session_manager' => 1,
        'recaptcha' => 0,
        'req_cap' => 1,
        'req_num' => 1,
        'email_login' => 2,
        'min_pw' => 5,
        'max_pw' => 32,
        'min_un' => 5,
        'max_un' => 30,
        'pwl_length' => 5,
        'redirect_uri_after_login' => 'users/account.php',
        'registration' => 1,
        'join_vericode_expiry' => 24,
        'change_un' => 0,
        'reset_vericode_expiry' => 120,
        'err_time' => 20,
        'container_open_class' => 'container-fluid',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $applied = $this->db->query(
            "SELECT COUNT(*) AS cnt FROM phinxlog WHERE version = 20260817033111"
        )->first();

        if (!$applied || (int) $applied->cnt === 0) {
            $this->markTestSkipped(
                'Migration 20260817033111 has not been applied. Run: composer migrate'
            );
        }
    }

    /**
     * Every column the migration sets must match its expected default on
     * settings row id=1.
     */
    #[Group('integration')]
    #[Group('migration')]
    public function test_settingsRowOneHasBaselineDefaults(): void
    {
        $columns = implode(', ', array_map(
            static fn(string $col): string => "`{$col}`",
            array_keys(self::EXPECTED)
        ));

        $row = $this->db->query(
            "SELECT {$columns} FROM settings WHERE id = 1"
        )->first();

        $this->assertNotNull($row, 'settings row id=1 must exist');

        foreach (self::EXPECTED as $column => $expected) {
            $this->assertEquals(
                $expected,
                $row->$column,
                "settings.{$column} did not match the baseline default written by the migration"
            );
        }
    }
}
