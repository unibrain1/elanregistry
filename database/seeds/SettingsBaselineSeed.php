<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Create the single `settings` row (id = 1) that UserSpice reads on every page load.
 *
 * UserSpice treats `settings` as a one-row configuration table; with no row, the
 * framework bootstraps into an unusable state. This seed writes the real
 * ElanRegistry values the application depends on (see `ELAN_DEFAULTS` below)
 * and fills every remaining NOT NULL column that has no database default with
 * a type-appropriate placeholder.
 *
 * The remaining columns are discovered from `information_schema` rather than
 * being listed here, because `settings` gains columns with each UserSpice
 * upgrade: a hardcoded column list would silently rot into an
 * "SQLSTATE[HY000]: Field 'x' doesn't have a default value" failure on the next
 * version bump. Placeholders are intentionally dumb ('' / 0) — this seed
 * provisions a bootable install, not a configured one; anything a site actually
 * cares about belongs in $elanDefaults below.
 *
 * Fail-loud by design: an unrecognised NOT NULL column type, or an
 * `ELAN_DEFAULTS` key that no longer matches a real column, throws rather than
 * being guessed at or quietly skipped.
 *
 * Idempotent: does nothing if row id = 1 already exists.
 */
final class SettingsBaselineSeed extends AbstractSeed
{
    /**
     * Real ElanRegistry values the application depends on.
     *
     * @var array<string, string|int>
     */
    private const ELAN_DEFAULTS = [
        'site_name'              => 'Lotus Elan Registry',
        'template'               => 'customizer',
        'copyright'              => 'Lotus Elan Registry and UniBrain',
        'navigation_type'        => 0,
        'elan_image_dir'         => 'userimages/',
        'elan_image_max'         => 6,
        'permission_restriction' => 1,
        'session_manager'        => 1,
        'recaptcha'              => 0,
        'req_cap'                => 1,
        'req_num'                => 1,
        'email_login'            => 2,
        'min_pw'                 => 5,
        'max_pw'                 => 32,
        'min_un'                 => 5,
        'max_un'                 => 20,
    ];

    /** @var list<string> Column types that take '' as a safe placeholder. */
    private const TEXT_TYPES = ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext'];

    /** @var list<string> Column types that take 0 as a safe placeholder. */
    private const NUMERIC_TYPES = ['int', 'tinyint', 'smallint', 'mediumint', 'bigint'];

    public function run(): void
    {
        if ($this->fetchRow('SELECT id FROM `settings` WHERE id = 1') !== false) {
            return;
        }

        $columns = $this->fetchAll(
            "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'settings'"
        );

        if ($columns === []) {
            throw new RuntimeException(
                'SettingsBaselineSeed: the `settings` table has no columns in this schema. ' .
                'Run `composer migrate` before seeding.'
            );
        }

        $insertColumns    = ['`id`'];
        $placeholders     = ['?'];
        $bindings         = [1];
        $unmatchedDefaults = array_keys(self::ELAN_DEFAULTS);

        foreach ($columns as $column) {
            $name = (string) $column['COLUMN_NAME'];
            if ($name === 'id') {
                continue;
            }

            $quotedName = '`' . str_replace('`', '``', $name) . '`';

            if (array_key_exists($name, self::ELAN_DEFAULTS)) {
                $insertColumns[]   = $quotedName;
                $placeholders[]    = '?';
                $bindings[]        = self::ELAN_DEFAULTS[$name];
                $unmatchedDefaults = array_diff($unmatchedDefaults, [$name]);
                continue;
            }

            // Nullable columns and columns with a database default need no help.
            if ($column['IS_NULLABLE'] === 'YES' || $column['COLUMN_DEFAULT'] !== null) {
                continue;
            }

            $type = (string) $column['DATA_TYPE'];
            if (in_array($type, self::TEXT_TYPES, true)) {
                $placeholderValue = '';
            } elseif (in_array($type, self::NUMERIC_TYPES, true)) {
                $placeholderValue = 0;
            } else {
                throw new RuntimeException(
                    "SettingsBaselineSeed: `settings`.`{$name}` is NOT NULL with no default and has " .
                    "an unhandled type '{$type}'. Guessing a placeholder risks writing a value the " .
                    'application misreads — add an explicit entry to ELAN_DEFAULTS, or add the type ' .
                    'to TEXT_TYPES/NUMERIC_TYPES if a blank value is genuinely safe.'
                );
            }

            $insertColumns[] = $quotedName;
            $placeholders[]  = '?';
            $bindings[]      = $placeholderValue;
        }

        if ($unmatchedDefaults !== []) {
            throw new RuntimeException(
                'SettingsBaselineSeed: ELAN_DEFAULTS names columns that do not exist in `settings`: ' .
                implode(', ', $unmatchedDefaults) . '. Those values would be silently dropped — ' .
                'reconcile against the current schema before seeding.'
            );
        }

        $inserted = $this->execute(
            'INSERT INTO `settings` (' . implode(', ', $insertColumns) . ') ' .
            'VALUES (' . implode(', ', $placeholders) . ')',
            $bindings
        );

        if ($inserted !== 1) {
            throw new RuntimeException(
                "SettingsBaselineSeed: the INSERT reported {$inserted} affected rows, expected 1. " .
                'UserSpice cannot boot without `settings` row id = 1 — investigate before continuing.'
            );
        }
    }
}
