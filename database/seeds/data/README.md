# Seed data

CSV exports consumed by the Phinx seed classes in `database/seeds/`. Each file's
header row is verified against the seed's expected column order at seed-run
time — a mismatch aborts the seed rather than silently transposing values.

| File | Source | Rows |
| --- | --- | --- |
| `car_models.csv` | `car_models` table, live dev DB (`elanregi_spice`), 2026-08 | 23 |
| `elan_factory_info.csv` | `elan_factory_info` table, live dev DB (`elanregi_spice`), 2026-08 | 9,762 |

A CSV per table is a simple, reviewable-diff dependency for seed data — no
runtime parsing of a larger, multi-table SQL dump required.

To regenerate after updating the reference data in a real database, export
with column order matching the corresponding seed class's `COLUMNS` constant,
e.g.:

```sql
SELECT year_available_from, year_available_to, display_name, human_readable_short,
       series, variant, type_code, model_value
FROM car_models ORDER BY id;
```

Write the result with a header row and standard CSV quoting (PHP's `fputcsv()`
with default settings matches what the seed classes read).
