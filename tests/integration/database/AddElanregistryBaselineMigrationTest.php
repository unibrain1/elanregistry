<?php

declare(strict_types=1);

require_once __DIR__ . '/../IntegrationTestCase.php';

use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for migration 20260709000000_add_elanregistry_baseline
 *
 * Verifies the post-migration state of the 13 ElanRegistry tables the baseline
 * creates on top of stock UserSpice, plus the 3 car audit triggers it creates.
 * Structural drift on shared stock tables (collation conversions, column
 * changes) is not re-tested here — that is what schema-fidelity verification
 * (information_schema diff against dev, done before this migration is
 * committed) already covers. This suite targets the parts most likely to
 * regress silently on a future edit: table existence and audit-trigger
 * presence. Trigger *behavior* (INSERT/UPDATE/DELETE DML) is already covered
 * by CarsYearSmallintMigrationTest against the triggers' current form.
 */
#[Group('integration')]
#[Group('migration')]
final class AddElanregistryBaselineMigrationTest extends IntegrationTestCase
{
    /** The 13 tables the baseline migration creates, excluding phinxlog (Phinx-owned). */
    private const REGISTRY_TABLES = [
        'car_models',
        'car_transfer_requests',
        'cars',
        'cars_hist',
        'country',
        'deleted_accounts_archive',
        'elan_factory_info',
        'fix_script_runs',
        'notifications',
        'plg_db_explainer_columns',
        'plg_db_explainer_databases',
        'plg_db_explainer_tables',
        'plg_sendinblue',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        // Verify the migration has been applied by checking that car_models exists.
        // If it hasn't, skip the suite rather than failing with misleading assertion errors.
        $exists = $this->db->query(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'car_models'"
        )->first();

        if (!$exists || (int) $exists->cnt === 0) {
            $this->markTestSkipped(
                'Migration 20260709000000 has not been applied — car_models does not exist. ' .
                'Run: composer migrate'
            );
        }
    }

    /**
     * Every table the baseline migration creates must exist after migrating.
     */
    #[Group('integration')]
    #[Group('migration')]
    public function test_allRegistryTablesExist(): void
    {
        $placeholders = "'" . implode("','", self::REGISTRY_TABLES) . "'";
        $rows = $this->db->query(
            "SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ({$placeholders})"
        )->results();

        $found = array_map(static fn(object $r): string => $r->TABLE_NAME, $rows);

        foreach (self::REGISTRY_TABLES as $table) {
            $this->assertContains(
                $table,
                $found,
                "Table `{$table}` must exist after the baseline migration"
            );
        }
    }

    /**
     * All three car audit triggers must exist after the baseline migration
     * (they are later rebuilt without ModifiedBy by 20260710120000, but must
     * exist from this migration onward — CarsYearSmallintMigrationTest verifies
     * their final, post-rebuild form and behavior).
     */
    #[Group('integration')]
    #[Group('migration')]
    public function test_carAuditTriggersExist(): void
    {
        $triggers = $this->db->query(
            "SELECT TRIGGER_NAME
             FROM information_schema.TRIGGERS
             WHERE TRIGGER_SCHEMA = DATABASE()
               AND EVENT_OBJECT_TABLE = 'cars'
             ORDER BY TRIGGER_NAME"
        )->results();

        $triggerNames = array_map(static fn(object $t): string => $t->TRIGGER_NAME, $triggers);

        $this->assertContains('cars_insert', $triggerNames, 'cars_insert trigger must exist');
        $this->assertContains('cars_update', $triggerNames, 'cars_update trigger must exist');
        $this->assertContains('cars_delete', $triggerNames, 'cars_delete trigger must exist');
    }

    /**
     * car_models.model_value backs CarModelsSeed's idempotent INSERT IGNORE —
     * without the UNIQUE index, re-running the seed would duplicate rows
     * instead of silently skipping them.
     */
    #[Group('integration')]
    #[Group('migration')]
    public function test_carModelsTable_hasUniqueModelValueIndex(): void
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'car_models'
               AND INDEX_NAME = 'model_value'
               AND NON_UNIQUE = 0"
        )->first();

        $this->assertGreaterThan(
            0,
            (int) $row->cnt,
            'car_models.model_value must have a UNIQUE index'
        );
    }

    /**
     * cars_hist.car_id must exist and be indexed — Car audit-trail lookups
     * (e.g. CarRepository history queries) filter on it.
     */
    #[Group('integration')]
    #[Group('migration')]
    public function test_carsHistTable_hasIndexedCarIdColumn(): void
    {
        $column = $this->db->query(
            "SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'cars_hist'
               AND COLUMN_NAME = 'car_id'"
        )->first();
        $this->assertNotNull($column, 'Column cars_hist.car_id must exist');

        $index = $this->db->query(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'cars_hist'
               AND COLUMN_NAME = 'car_id'"
        )->first();
        $this->assertGreaterThan(0, (int) $index->cnt, 'cars_hist.car_id must be indexed');
    }
}
