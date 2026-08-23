# Elan Registry v2.29.3 Release Notes

**Release Date:** TBD
**Type:** Minor Release - Turn the Gates On (CI/testing infrastructure)

## Required Actions After Deployment

[To be filled in as issues are completed — likely includes confirming the
new CI integration-test service container is healthy on its first run.]

## Technical Changes

Internal tooling, CI, and vendored-dependency changes with no user-facing or
admin-facing behavior change.

- **Integration suite no longer silently exits 0 with no output when the test DB is unreachable** ([#1591](https://github.com/elan-registry/registry/issues/1591))
- **Integration suite is now a non-bypassable gate at pre-push** ([#1439](https://github.com/elan-registry/registry/issues/1439))
- **`/finish-milestone`/`/finish-issue` now verify the CI deep-review posted a comment, instead of assuming it ran** ([#1724](https://github.com/elan-registry/registry/issues/1724))
- **DataTables vendored bundle rebuilt for coordinated bs5/fixedheader/responsive version bump** ([#1741](https://github.com/elan-registry/registry/issues/1741))
- **MapLibre GL vendored bundle rebuilt for 4.7.1 to 6.4.1 bump** ([#1742](https://github.com/elan-registry/registry/issues/1742))
- **WIP: @versatiles/style vendored output rebuilt for 5.13.0 to 5.13.1 bump** ([#1743](https://github.com/elan-registry/registry/issues/1743))
- **WIP: Remaining dead `elan_*_cdn`/`fun` settings columns dropped** ([#1734](https://github.com/elan-registry/registry/issues/1734))

## Issues Resolved

- [#1439](https://github.com/elan-registry/registry/issues/1439) — ci: run integration suite against MySQL service container (non-bypassable gate)
- [#1591](https://github.com/elan-registry/registry/issues/1591) — test: integration suite exits 0 with no output when DB is unreachable
- [#1724](https://github.com/elan-registry/registry/issues/1724) — ci: a milestone PR can merge with its deep review never having run, undetected
- [#1734](https://github.com/elan-registry/registry/issues/1734) — tech-debt: drop remaining dead elan_*_cdn settings columns (jquery, bootstrap, popper, fontawesome, bootswatch, datatables, datepicker, chartjs) and `fun`
- [#1741](https://github.com/elan-registry/registry/issues/1741) — chore: rebuild DataTables vendored bundle for coordinated bs5/fixedheader/responsive version bump
- [#1742](https://github.com/elan-registry/registry/issues/1742) — chore: rebuild vendored MapLibre GL bundle for maplibre-gl 4.7.1 to 6.4.1 bump
- [#1743](https://github.com/elan-registry/registry/issues/1743) — chore: rebuild vendored @versatiles/style output for 5.13.0 to 5.13.1 bump
