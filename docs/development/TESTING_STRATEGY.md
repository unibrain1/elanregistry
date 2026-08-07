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
  your own project code (a mock `DB`, mock `CarModel`, etc. tests the mock,
  not the application). Mocking third-party/framework boundaries (like
  UserSpice's `DB` class itself) is fine when the goal is isolating your own
  code's logic from a real database — the anti-pattern is mocking *your own*
  classes.
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

`tests/regression/` is **not** a fourth tier — it's a `#[Group('regression')]`
tag applied to tests that live in whichever tier fits them, run via
`composer test:regression`. Treat "which tier" and "is it regression-tagged"
as orthogonal questions.

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
