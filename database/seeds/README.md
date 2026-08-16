# Database Seeds

Phinx seed classes (`Phinx\Seed\AbstractSeed`) that populate reference and
configuration data after `composer migrate` has built the schema. Run via
`composer seed:run` (all seeds) or `vendor/bin/phinx seed:run -s <ClassName>`
(one seed) — see `scripts/provision-schema.sh` for how provisioning selects
which seeds run by default.

Every seed here is idempotent: re-running is always a safe no-op once the
data already exists.

## Data format: CSV vs. inline PHP

- **Multi-row tabular data** → a CSV under `data/`, read at seed-run time.
  `CarModelsSeed` (23 rows) and `ElanFactoryInfoSeed` (9,762 rows) both work
  this way — see `data/README.md`. A CSV gives a clean, reviewable diff per
  row and scales to large row counts without bloating the PHP file.
- **A single row** → write the values directly as a PHP array/const in the
  class. `NoownerSeed` (one system account, via `$this->insert('users', [...])`)
  and `SettingsBaselineSeed` (`ELAN_DEFAULTS`) both do this — a CSV with one
  header row and one data row would be more awkward to read and review than
  the equivalent inline array.

Pick whichever matches the shape of the data you're adding, not whichever an
existing seed happens to use.

## Run order

Seeds are normally independent — `-s <ClassName>` bypasses Phinx's
`getDependencies()` ordering, so don't rely on it. One exception:
`BaselinePermissionsSeed` must run before `PageRegistrationSeed`, since the
latter inserts `permission_page_matches` rows referencing
`permissions.id = 3`, which only the former creates. `provision-schema.sh`
discovers seeds via an alphabetical filesystem glob, and
`BaselinePermissionsSeed` is named specifically to sort ahead of
`PageRegistrationSeed` — see that class's docblock for the full rationale.
Any new seed with a similar ordering requirement must either sort correctly
by name or the discovery mechanism needs to grow explicit ordering support.

## Exceptions, deliberately

Every seed throws bare `\RuntimeException` on failure, not a typed exception
extending `ElanRegistryException` (CLAUDE.md/ERROR_HANDLING.md's normal
requirement for app code). Seeds run under Phinx's CLI, outside the
application's error stack — no `logger()`, no `ApiResponse`, no request
context for a typed exception to carry. This is a deliberate exception to the
app-code convention, not an oversight; don't "fix" it by adding
`ElanRegistryException` here, and don't use it as precedent in `app/`/`usersc/`.
