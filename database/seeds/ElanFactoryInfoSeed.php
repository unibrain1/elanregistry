<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Populate `elan_factory_info` with the 9,762 Lotus factory production records.
 *
 * This is real display data — the factory build sheet shown alongside a car's
 * chassis number — not test scaffolding. Integration tests insert their own
 * minimal fixture rows (see `FactoryRegistryLinkIntegrationTest`), so nothing in
 * the test suite needs the bulk dataset.
 *
 * **Not part of the default provisioning run.** `vendor/bin/phinx seed:run` with
 * no `-s` flag executes every seed class it discovers, this one included, and
 * loading ~780 KB of production records makes test-schema provisioning needlessly
 * slow for data no test reads. `scripts/provision-schema.sh` therefore names the
 * seeds it wants individually and deliberately omits this one unless `--full` is
 * passed. Outside that script, a real install must invoke it explicitly:
 *
 * ```
 * vendor/bin/phinx seed:run -s ElanFactoryInfoSeed
 * ```
 *
 * Idempotent twice over: the seed re-attempts every run, checking row count
 * (not mere presence) against EXPECTED_ROWS before skipping, so an
 * interrupted prior run (killed mid-batch) self-heals instead of being
 * permanently mistaken for complete; and the inserts themselves are
 * `INSERT IGNORE` against the primary key, so re-running against an already
 * complete table is a safe no-op. Note the semantic difference from
 * re-running a full data refresh: this seed provisions an empty table and
 * never touches rows that already exist. Correcting existing factory data is
 * a data fix, not a provisioning step, and belongs in `app/admin/scripts/fix/`.
 */
final class ElanFactoryInfoSeed extends AbstractSeed
{
    /**
     * Source file, relative to this class's own directory: a checked-in CSV
     * export of the live `elan_factory_info` table (see
     * `database/seeds/data/README.md` for provenance).
     *
     * A CSV keeps this independent of any particular SQL dump's format and
     * produces a reviewable diff when factory data changes. Regex-parsing a
     * large SQL INSERT block is a real trap: a lazy `.*?` spanning a
     * multi-hundred-KB tuple list blows PCRE's default `pcre.backtrack_limit`
     * of 1,000,000, and even an anchor+strpos workaround still leaves a
     * fragile, file-format-coupled dependency for no benefit over a CSV.
     */
    private const SOURCE_FILE = __DIR__ . '/data/elan_factory_info.csv';

    /**
     * Rows in the source file. Asserted, not assumed — a mismatch means the
     * checked-in CSV was edited unexpectedly and deserves review.
     */
    private const EXPECTED_ROWS = 9762;

    /**
     * Rows per INSERT. Keeps each statement comfortably under a 1 MB
     * `max_allowed_packet`, where the full ~390 KB CSV as a single statement
     * would not be once re-expanded to SQL placeholders and bindings.
     */
    private const BATCH_SIZE = 1000;

    /**
     * Column order in both the CSV header and the INSERT — verified to match
     * at runtime so a reordered CSV fails loudly instead of silently
     * transposing values.
     */
    private const COLUMNS = [
        'id',
        'year',
        'month',
        'batch',
        'type',
        'serial',
        'suffix',
        'engineletter',
        'enginenumber',
        'gearbox',
        'color',
        'builddate',
        'note',
    ];

    public function run(): void
    {
        $existing = (int) $this->fetchRow('SELECT COUNT(*) AS n FROM `elan_factory_info`')['n'];
        if ($existing >= self::EXPECTED_ROWS) {
            return;
        }

        $rows    = $this->readCsv();
        $columns = '`' . implode('`, `', self::COLUMNS) . '`';

        foreach (array_chunk($rows, self::BATCH_SIZE) as $chunk) {
            $rowPlaceholders = '(' . implode(', ', array_fill(0, count(self::COLUMNS), '?')) . ')';
            $placeholders    = implode(', ', array_fill(0, count($chunk), $rowPlaceholders));
            $bindings        = array_merge(...$chunk);

            $this->execute(
                "INSERT IGNORE INTO `elan_factory_info` ({$columns}) VALUES {$placeholders}",
                $bindings
            );
        }

        $loaded = (int) $this->fetchRow('SELECT COUNT(*) AS n FROM `elan_factory_info`')['n'];
        if ($loaded < self::EXPECTED_ROWS) {
            throw new RuntimeException(
                'ElanFactoryInfoSeed: expected at least ' . self::EXPECTED_ROWS . ' rows in ' .
                "`elan_factory_info` after seeding, found {$loaded}. Factory data drives " .
                'the chassis-number lookup — investigate before trusting it.'
            );
        }
    }

    /**
     * @return list<list<string>> Data rows, header excluded. Empty strings from
     *     the CSV (blank cells) are passed through as `''`, which the app's
     *     existing `elan_factory_info` columns already treat as "unknown" —
     *     every column here is a non-nullable varchar/date, same as the source data.
     */
    private function readCsv(): array
    {
        $handle = @fopen(self::SOURCE_FILE, 'r');
        if ($handle === false) {
            throw new RuntimeException(
                'ElanFactoryInfoSeed: could not read ' . self::SOURCE_FILE .
                '. Check the file exists and is readable.'
            );
        }

        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if ($header !== self::COLUMNS) {
            fclose($handle);
            throw new RuntimeException(
                'ElanFactoryInfoSeed: CSV header does not match the expected column order. ' .
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
                    "ElanFactoryInfoSeed: row {$lineNumber} of " . self::SOURCE_FILE . ' has ' . count($row) .
                    " field(s), expected {$expectedFieldCount}. A short/blank row would otherwise " .
                    'shift every subsequent binding silently.'
                );
            }
            $rows[] = $row;
        }
        fclose($handle);

        if (count($rows) !== self::EXPECTED_ROWS) {
            throw new RuntimeException(
                'ElanFactoryInfoSeed: read ' . count($rows) . ' rows from ' . self::SOURCE_FILE .
                ', expected ' . self::EXPECTED_ROWS . '. Either the dataset changed (update ' .
                'EXPECTED_ROWS) or the CSV was truncated.'
            );
        }

        return $rows;
    }
}
