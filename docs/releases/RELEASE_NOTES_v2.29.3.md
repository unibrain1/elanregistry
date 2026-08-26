# Elan Registry v2.29.3 Release Notes

**Release Date:** TBD
**Type:** Minor Release - Turn the Gates On (CI/testing infrastructure)

## Required Actions After Deployment

None. Run the standard `composer migrate` deploy step to apply the settings-column
drop migration ([#1734](https://github.com/elan-registry/registry/issues/1734)) —
no manual data backfill or config change is needed beyond that. The new
pre-push integration-test gate ([#1439](https://github.com/elan-registry/registry/issues/1439))
and CI service container run in local/CI environments only and require no
production or test-server action.

## Technical Changes

Internal tooling, CI, and vendored-dependency changes with no user-facing or
admin-facing behavior change.

- **Integration suite no longer silently exits 0 with no output when the test DB is unreachable** ([#1591](https://github.com/elan-registry/registry/issues/1591))
- **Integration suite is now a non-bypassable gate at pre-push** ([#1439](https://github.com/elan-registry/registry/issues/1439))
- **Fixed unguarded transaction in `CarMergeTest.php` that leaked into subsequent integration tests** — an unguarded `beginTransaction()`/`rollback()` block could leave a transaction open if an assertion threw first, silently corrupting whichever test ran next on the suite's shared DB connection; added a `try`/`finally` guard plus a defense-in-depth check in `IntegrationTestCase::tearDown()` that loudly flags any transaction still open at test end ([#1745](https://github.com/elan-registry/registry/issues/1745))
- **`/finish-milestone`/`/finish-issue` now verify the CI deep-review posted a comment, instead of assuming it ran** ([#1724](https://github.com/elan-registry/registry/issues/1724))
- **DataTables vendored bundle rebuilt for coordinated bs5/fixedheader/responsive version bump** ([#1741](https://github.com/elan-registry/registry/issues/1741))
- **MapLibre GL vendored bundle rebuilt for 4.7.1 to 6.4.1 bump** ([#1742](https://github.com/elan-registry/registry/issues/1742))
- **@versatiles/style vendored output rebuilt for 5.13.0 to 5.13.1 bump** ([#1743](https://github.com/elan-registry/registry/issues/1743))
- **Remaining dead `elan_*_cdn`/`fun` settings columns dropped** ([#1734](https://github.com/elan-registry/registry/issues/1734))
- **Pre-push integration-test gate no longer runs the full suite on empty/first pushes of new issue branches** ([#1751](https://github.com/elan-registry/registry/issues/1751))
- **Cleaned up useless/brittle/redundant `tests/unit/` coverage** — deleted tests that only asserted on comments/docblocks/unrelated markdown text rather than actual behavior, removed exact-duplicate test classes, consolidated regression tests fully subsumed by a newer test (after porting one uncovered edge case), and rewrote `ServerGlobalsTest` to exercise real `$_SERVER`-processing behavior via an isolated subprocess instead of grepping file text ([#1758](https://github.com/elan-registry/registry/issues/1758))
- **Cleaned up useless/brittle/redundant `tests/integration/` coverage** — deleted a test that only asserted on raw SQL/column existence rather than the real admin endpoints it claimed to test (coverage already exists via a source-guard and a Playwright HTTP test); extracted a shared `InputSanitizer::stripHeaderInjectionChars()` production helper to replace 11 duplicated inline regex call sites for SMTP header-injection prevention, with both sanitization test files updated to call the real helper instead of mirroring the regex; isolated 7 live-network geocoding tests behind an opt-in `live-network` PHPUnit group so a local integration run no longer depends on third-party API availability; replaced a `sleep(1)` timing test with a deterministic seeded timestamp; merged two near-duplicate ownerless-accounts test files into one parameterized test (mirroring the #1758 unit-tier consolidation); and removed ~164 lines of duplicated fixture-setup literals in the car-transfer workflow test ([#1759](https://github.com/elan-registry/registry/issues/1759))
- **Cleaned up false-positive/brittle/redundant `tests/playwright/` coverage** — fixed several tests that were "green" without ever executing their assertions (vacuous `if (count > 0) { expect(...) }` guards in `ajax-endpoints.spec.js`, `ui-consistency.spec.js`, `login-functionality.spec.js`, `e2e/factory-registry-link.spec.js`, `functionality.spec.js`, and `e2e/logged-in.spec.js`), including a real (previously always-skipped) session-cookie security assertion (`httpOnly`/`sameSite`/`secure`) and a corrected CSRF-field selector; deleted `security.spec.js` (its one test was unconditionally vacuous and fully superseded by `csp-validation.spec.js`/`clickjacking.spec.js`); consolidated `clickjacking.spec.js` from 9 tests to 4 with identical coverage, and de-duplicated Google Maps domain-request checks and page-title assertions into two new shared `auth-helper.js` functions; centralized 5 scattered hardcoded fixture car IDs into a new `tests/playwright/fixtures.js`; fixed a brittle exact-locale-date-string assertion in `maps-charts.spec.js`; and replaced several hardcoded `waitForTimeout()` sleeps with condition-based waits ([#1760](https://github.com/elan-registry/registry/issues/1760))
- **Found in passing: local rate-limit dev-override no longer lost on every Rate Limiting Dashboard save** — the dashboard fully overwrites `usersc/includes/rate_limits.php` on every save with no merge/preservation logic, silently destroying any manually-added local-dev rate-limit relaxation. Moved that logic to a new `usersc/includes/rate_limits_dev_override.php` (never touched by the dashboard) and included it unconditionally from `usersc/includes/loader.php`; gated behind `US_ENVIRONMENT=development` (git-ignored `.env`, defaults to production/no-op when unset) ([#1760](https://github.com/elan-registry/registry/issues/1760))
- **PHP error log destination configured for production and test** — added an `HTTP_HOST`-conditional block to the root `.htaccess` resolving each environment's error log path at Apache request-time (no deploy-time templating exists), following the existing `robots-test.txt` conditional precedent ([#1768](https://github.com/elan-registry/registry/issues/1768))

## Issues Resolved

- [#1439](https://github.com/elan-registry/registry/issues/1439) — ci: run integration suite against MySQL service container (non-bypassable gate)
- [#1745](https://github.com/elan-registry/registry/issues/1745) — fix: unguarded transaction in CarMergeTest.php leaks into subsequent integration tests
- [#1591](https://github.com/elan-registry/registry/issues/1591) — test: integration suite exits 0 with no output when DB is unreachable
- [#1724](https://github.com/elan-registry/registry/issues/1724) — ci: a milestone PR can merge with its deep review never having run, undetected
- [#1734](https://github.com/elan-registry/registry/issues/1734) — tech-debt: drop remaining dead elan_*_cdn settings columns (jquery, bootstrap, popper, fontawesome, bootswatch, datatables, datepicker, chartjs) and `fun`
- [#1741](https://github.com/elan-registry/registry/issues/1741) — chore: rebuild DataTables vendored bundle for coordinated bs5/fixedheader/responsive version bump
- [#1742](https://github.com/elan-registry/registry/issues/1742) — chore: rebuild vendored MapLibre GL bundle for maplibre-gl 4.7.1 to 6.4.1 bump
- [#1743](https://github.com/elan-registry/registry/issues/1743) — chore: rebuild vendored @versatiles/style output for 5.13.0 to 5.13.1 bump
- [#1751](https://github.com/elan-registry/registry/issues/1751) — ci: pre-push integration-test gate runs full suite on empty issue-branch pushes (wrong merge-base fallback)
- [#1758](https://github.com/elan-registry/registry/issues/1758) — tech-debt: clean up useless/brittle/redundant tests/unit/ coverage
- [#1759](https://github.com/elan-registry/registry/issues/1759) — tech-debt: clean up useless/brittle/redundant tests/integration/ coverage
- [#1760](https://github.com/elan-registry/registry/issues/1760) — tech-debt: fix false-positive/brittle/redundant tests/playwright/ specs
- [#1768](https://github.com/elan-registry/registry/issues/1768) — chore: add PHP error log destination to .htaccess for prod and test
