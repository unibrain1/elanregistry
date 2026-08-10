# Testing Strategy

This document covers **why** the test suite is organized the way it is and
the UserSpice-specific behaviors that any new test needs to account for.
For **how** to run or write tests — commands, directory layout, fixture
helpers, troubleshooting — see [`tests/README.md`](../../tests/README.md)
and [`docs/testing/TESTING.md`](../testing/TESTING.md) (Playwright specifics:
[`docs/testing/PLAYWRIGHT_E2E.md`](../testing/PLAYWRIGHT_E2E.md)). This doc
doesn't repeat that material — it's the single place that documents the
*rules*, not the commands.

## Tier Architecture

Three tiers, each with a distinct purpose and a hard boundary:

- **Unit (`tests/unit/`)** — fast, no database. Test real application
  classes with real logic; never write a hand-rolled mock/reimplementation of
  your own project code (a mock `CarModel`, mock `CarRepository`, etc. tests
  the mock, not the application). Mocking third-party/framework boundaries
  (like UserSpice's `DB` class itself) is fine when the goal is isolating
  your own code's logic from a real database — the anti-pattern is mocking
  *your own* classes (e.g. `CarRepository`). Construct the real class against
  a mocked `DB` instead, so the class's own translation logic (DB response →
  return value/exception) actually executes.

  `tests/bootstrap-unit.php`'s global `DB` class is intentionally retained as
  a thin, type-shaped stand-in — every class constructed in the unit tier
  that type-hints `DB` (`CarRepository`, `CarTransferRepository`,
  `RegistrationRecoveryNotifier`, and more) needs a class literally named
  `DB` to resolve, and PHPUnit's `createMock(DB::class)`/`createStub(DB::class)`
  needs one too to build a double against — a class can't be deleted just
  because most of its default behavior was (#1441). The convention, per
  `tests/unit/cars/services/CarRepositoryTest.php`'s `makeDbMock()`: build a
  per-test `createMock(DB::class)`/`createStub(DB::class)` double configured
  for exactly what that test needs, rather than relying on the shared
  singleton's canned defaults for anything beyond "happy path, don't care
  about the exact shape." A follow-up (extracting a narrow
  `ElanRegistry\DatabaseInterface` for these classes to type-hint instead of
  `DB`) would let the shell disappear for real — tracked in #1585.

  Model-combination existence can't be proven in the unit tier — the shared
  `DB` mock has no `car_models` branch, so `CarModel::exists()` always returns
  `false` there (#1446). Assert it in
  `tests/integration/cars/services/CarValidatorModelTest.php` instead.

  UserSpice's own bare (non-namespaced) classes under `users/classes/` are a
  different case, and the rule there is absolute: they can **never** be loaded
  for real in the unit tier, no matter how dependency-free they are. The entire
  `users/` tree is `.gitignore`'d (`users/**`) — it is a manually installed
  upstream checkout, absent from every CI checkout and from `composer install`.
  A `require_once $projectRoot . '/users/classes/Token.php'` in
  `tests/bootstrap-unit.php` works on a developer machine and fatals in CI with
  "Failed to open stream: No such file or directory" (#1554). This applies even
  to `Token`, `Input`, `Config` and `Session`, which touch nothing but
  superglobals and `$GLOBALS['config']` — the constraint is file availability,
  not runtime dependencies. So `tests/bootstrap-unit.php` declares raw stubs for
  `Token` and `Input`, and unit tests may only assert those stubs' own contract,
  never real CSRF crypto or real `htmlspecialchars()` encoding. Real behavior for
  these classes is proven in the integration tier, where `users/init.php` loads
  the genuine framework — see
  `tests/integration/TokenAndInputSecurityTest.php`. That coverage is
  developer-local, not CI-enforced: `tests.yml` never runs
  `phpunit-integration.xml` (unit and regression only), so this proof runs via
  `composer test:integration`/`test:full` before merge, not automatically on
  every push (#1591).

  `tests/unit/uploads/_is_uploaded_file_namespace_overrides.php` relaxes
  `ElanRegistry\Car\is_uploaded_file()` (via PHP's namespace-scoped function
  resolution) so `CarImageProcessor::validateFileUpload()` is testable
  without a real HTTP upload. Because PHP has no per-file function scoping,
  once `FileUploadSecurityTest.php` requires it, the relaxed check is
  declared for the rest of that PHPUnit process (`processIsolation="false"`).
  If you add a new test elsewhere that exercises `validateFileUpload()`'s
  `is_uploaded_file()` branch, be aware a file merely on disk
  (`file_exists()`) will pass that check too — it isn't isolated to
  `FileUploadSecurityTest.php`.

  When a test needs a bare global-namespace double for a real UserSpice class
  (not a namespaced project class) to satisfy a type hint the production code
  can't be changed to relax, declare it in its own `_`-prefixed file and add
  that file to `phpstan.neon`'s `excludePaths`, rather than inline in the
  test class — e.g. `tests/unit/auth/_User_test_double.php` standing in for
  `\User`. PHP has no per-file scoping for global classes: once `tests/`
  entered PHPStan's scan path
  (#1555), an inline double becomes *the* definition PHPStan uses for that
  class everywhere in the codebase, not just within the test that declares it
  (#1566). Excluding the file keeps the class genuinely unanalyzable to
  PHPStan instead, which the project's existing `ignoreErrors` patterns for
  UserSpice runtime classes already account for.
- **Integration (`tests/integration/`)** — real database, real UserSpice
  framework. Every test creates the fixtures it depends on
  (`IntegrationTestCase::createTestUser()` / `createTestCar()`) and cleans
  them up in `tearDown()`. Never assume ambient data exists — see
  `tests/README.md`'s "Test Data Isolation" section for the do/don't
  examples.
- **Browser/E2E (`tests/playwright/`)** — golden paths and security-critical
  flows only (CSRF, auth, XSS). Not a substitute for unit/integration
  coverage of business logic — browser tests are expensive and should stay
  narrow.

`tests/regression/` is **not** a fourth tier in the unit/integration/E2E
sense — it's orthogonal in intent, tracking "was this specific bug fixed"
rather than a class or flow. But mechanically it runs entirely under the
unit bootstrap: `phpunit-unit.xml` scopes the `Regression` testsuite to the
`tests/regression/` directory (directory-based, not a `#[Group]` tag), and
that directory loads via `tests/bootstrap-unit.php` — mocks only, no
database, same as `tests/unit/`. A regression test that needs a real
database has to live in `tests/integration/` instead; it isn't a `composer
test:regression` test. A new mock-compatible regression test belongs in
`tests/regression/` (copy
`RegressionTestTemplate.php`, see `tests/regression/README.md`) — a
`#[Group('regression')]` attribute alone on a test living elsewhere will
**not** be picked up by `composer test:regression`.

## UserSpice `DB` Class Conventions

UserSpice ships no test suite and no testing conventions of its own — the
following is derived directly from reading `users/classes/DB.php` (upstream,
do not modify — see `CLAUDE.md`'s Template Customization Rules), not from
UserSpice documentation. This is the source of truth for how to write
assertions against it.

- **Connection failures kill the request.** `DB::__construct()` catches
  `PDOException` on connect and calls `die("Could not connect to database...")`
  (uncatchable, no exception propagates). There is no framework-native way to
  intercept or test the first-connection-fails path — don't write a test that
  expects an exception here.
- **`query()`/`action()` never throw for execute-time failures.** In
  `DB::query()`, `$pdo->prepare()` runs *outside* the try/catch, so a
  prepare-time failure (malformed SQL) throws an uncaught `PDOException`.
  But `$query->execute()` runs *inside* the try/catch, and a failure there is
  caught and converted into internal state (`$this->_error = true`) — it does
  **not** throw. Always check `->error()` / `->errorString()` after a query
  to assert failure; wrapping a call in `try/catch` only catches prepare-time
  syntax errors, not execution failures like constraint violations.
- **`first()` returns `[]`, never `null`.** `DB::first()` is
  `$this->count() > 0 ? $this->results($assoc)[0] : []`. Asserting
  `assertNotNull($result)` on a "no row found" case always passes — it's
  testing the wrong thing. Use `assertIsObject()` / `assertNotEmpty()` (or
  `assertSame([], $result)` for the not-found case) instead.
- **`sql_mode` is deliberately `''` (strict mode off).** `DB::__construct()`
  sets `PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode = ''"` unless
  `Config::get('mysql/options')` overrides it. A raw diagnostic MySQL
  connection (e.g. a manual `mysql` CLI session) defaults to strict mode and
  will behave differently — don't validate fixture data against a connection
  that isn't going through `DB`.

## Provisioning

Test, dev, CI, and prod all provision from the same mechanism: the baseline
migration + `composer migrate` + `composer seed:run`
(`scripts/provision-schema.sh` wraps the full sequence for a fresh schema).
See `tests/README.md`'s "Provisioning a Test Schema" and "Database Fixtures"
sections for the full walkthrough, seed class list, and what
`tests/bootstrap-integration.php` verifies vs. seeds itself.

## Related Work

Framework-divergence documentation (which upstream UserSpice files this
project has customized, and why) is tracked separately in #1460
(`UPSTREAM_DIVERGENCES.md`) — not yet written as of this doc. Once it lands,
cross-reference it here rather than duplicating UserSpice-quirk content in
two places.
