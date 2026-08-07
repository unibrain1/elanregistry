<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Populate `car_models` with the 23 Lotus Elan model definitions.
 *
 * Required by integration tests and by every car add/edit form — an empty
 * `car_models` table makes the model dropdown silently empty, so this seed is
 * part of the default seed run.
 *
 * Rows come from `database/seeds/data/car_models.csv`, a checked-in export of
 * the live `car_models` table (see `database/seeds/data/README.md` for
 * provenance). A CSV with a fixed column order is a simple dependency and
 * produces a reviewable diff when the model list changes — see
 * `ElanFactoryInfoSeed` for why parsing a large SQL dump at seed-run time is
 * the wrong alternative.
 *
 * Idempotent: `INSERT IGNORE` against the `model_value` and
 * `series`/`variant`/`type_code` unique keys, so re-running is a no-op.
 */
final class CarModelsSeed extends AbstractSeed
{
    /**
     * Source file, relative to this class's own directory.
     */
    private const SOURCE_FILE = __DIR__ . '/data/car_models.csv';

    /**
     * Column order in both the CSV header and the INSERT — verified to match
     * at runtime so a reordered CSV fails loudly instead of silently
     * transposing values.
     */
    private const COLUMNS = [
        'year_available_from',
        'year_available_to',
        'display_name',
        'human_readable_short',
        'series',
        'variant',
        'type_code',
        'model_value',
    ];

    /**
     * Rows in the source file. Asserted, not assumed — a mismatch means the
     * checked-in CSV was edited unexpectedly and deserves review.
     */
    private const EXPECTED_ROWS = 23;

    public function run(): void
    {
        $rows = $this->readCsv();

        $columns          = '`' . implode('`, `', self::COLUMNS) . '`';
        $rowPlaceholders  = '(' . implode(', ', array_fill(0, count(self::COLUMNS), '?')) . ')';
        $placeholders     = implode(', ', array_fill(0, count($rows), $rowPlaceholders));
        $bindings         = array_merge(...$rows);

        // INSERT IGNORE keeps this idempotent against the model_value and
        // series/variant/type_code unique keys without a pre-check per row —
        // but it also downgrades a truncation or invalid-value error to a
        // warning, so a CSV row exceeding a column's length would silently
        // load short. The count assertion below catches that: it must reach
        // EXPECTED_ROWS, not just be non-zero.
        $this->execute(
            "INSERT IGNORE INTO `car_models` ({$columns}) VALUES {$placeholders}",
            $bindings
        );

        $count = (int) $this->fetchRow('SELECT COUNT(*) AS n FROM `car_models`')['n'];
        if ($count < self::EXPECTED_ROWS) {
            throw new RuntimeException(
                'CarModelsSeed: expected at least ' . self::EXPECTED_ROWS . ' rows in `car_models` ' .
                "after seeding, found {$count}. INSERT IGNORE may have silently dropped a row " .
                '(truncation, duplicate key) — investigate before running integration tests.'
            );
        }
    }

    /**
     * @return list<list<string>> Data rows, header excluded.
     */
    private function readCsv(): array
    {
        $handle = @fopen(self::SOURCE_FILE, 'r');
        if ($handle === false) {
            throw new RuntimeException(
                'CarModelsSeed: could not read ' . self::SOURCE_FILE . '. Check the file exists and is readable.'
            );
        }

        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if ($header !== self::COLUMNS) {
            fclose($handle);
            throw new RuntimeException(
                'CarModelsSeed: CSV header does not match the expected column order. ' .
                'Expected [' . implode(', ', self::COLUMNS) . '], found [' . implode(', ', (array) $header) . '].'
            );
        }

        $expectedFieldCount = count(self::COLUMNS);
        $rows = [];
        $lineNumber = 1;
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $lineNumber++;
            if (count($row) !== $expectedFieldCount) {
                fclose($handle);
                throw new RuntimeException(
                    "CarModelsSeed: row {$lineNumber} of " . self::SOURCE_FILE . ' has ' . count($row) .
                    " field(s), expected {$expectedFieldCount}. A short/blank row would otherwise " .
                    'shift every subsequent binding silently.'
                );
            }
            $rows[] = $row;
        }
        fclose($handle);

        if (count($rows) !== self::EXPECTED_ROWS) {
            throw new RuntimeException(
                'CarModelsSeed: read ' . count($rows) . ' rows from ' . self::SOURCE_FILE .
                ', expected ' . self::EXPECTED_ROWS . '. Either the dataset changed (update ' .
                'EXPECTED_ROWS) or the CSV was truncated.'
            );
        }

        return $rows;
    }
}
