# Elan Registry v2.30.1 Release Notes

**Release Date:** September 4, 2026
**Type:** Patch Release - Verification System Refresh: Owner-Field Integrity

An owner who updates their name, email, website, or location sees that change
on every car they own, and the registry's records stop drifting from what the
owner last told it. Every owner-contact field mirrored onto `cars` now syncs
from the profile on every edit path, and the privacy policy is brought in line
with what the registry actually does with member data (Brevo, delivery
telemetry, cross-car sync, retention). Two production defects ship alongside:
the car-list CSRF stale-token failure and the admin merge action leaving
`userimages/` files behind. The integration-test gate is decoupled from
UserSpice so CI can run it.

## Required Actions BEFORE Deployment

> **Stop.** This release cannot be safely deployed without completing step 1
> first. The migration it ships is not reversible in effect — see below.

### 1. Take a full database backup — mandatory, before anything else

This release ships a schema migration that rewrites six columns on `cars` and
`cars_hist` (the two largest tables) from `TIMESTAMP` to `DATETIME`, rebuilds the
three `cars` audit triggers, normalizes 15 legacy partial dates in `cars_hist`, and
backfills `owner_last_updated` on every active car. **Do not push this release
without a verified full backup in hand.** The documented remedy if timestamps come
back shifted is to restore from backup — there is no forward fix.

```bash
mysqldump -u <user> -p --single-transaction --routines --triggers \
  <database> > elanregistry-pre-v2.30.1-$(date +%Y%m%d-%H%M%S).sql
```

Confirm the dump is non-empty and contains the `cars` and `cars_hist` CREATE TABLE
statements before proceeding.

### 2. Record a timestamp baseline for the post-deploy check

The conversion is value-preserving only if it runs under matching clocks, and a
shifted-but-well-formed timestamp passes every automated check in this release. The
only way to catch one is to compare against values recorded beforehand:

```sql
SELECT id, ctime, mtime FROM cars ORDER BY id LIMIT 3;
```

Keep that output. Step 4 compares against it.

## Required Actions During Deployment

### 3. Run the migration

```bash
composer migrate
```

The migration **aborts by design** if MySQL's `NOW()` differs by more than 120
seconds from PHP evaluated in `America/Los_Angeles` — the zone `users/init.php`
pins on every web request, and therefore the zone every stored value was written
in. Converting `TIMESTAMP` to `DATETIME` freezes each value as a wall-clock string
in the session's timezone, so a mismatched clock would shift every timestamp in
both tables permanently and silently (the shifted values are still well-formed).
A failed guard is not recorded in `phinxlog`, so the migration is safely
re-runnable once the environment is corrected.

The guard deliberately does **not** compare against ambient CLI PHP. Measured on
production 2026-09-05, three clocks disagree:

| Clock | Zone | Role |
| --- | --- | --- |
| MySQL | `SYSTEM` → MST (−7) | stores the values |
| Web PHP | `America/Los_Angeles` → PDT (−7) | **wrote** the values |
| CLI PHP | `America/New_York` (−4) | irrelevant to the data |

MySQL and web PHP agree, so the September conversion is value-preserving. Comparing
CLI PHP instead would abort the deploy on a 3-hour skew that cannot affect the data.

**Seasonal caveat:** MST does not observe DST but `America/Los_Angeles` does. From
early November to mid-March the two diverge by one hour and the guard will correctly
abort. If this migration must be re-run in that window — including during a
partial-failure recovery — align the MySQL session zone with the application zone
first; do not widen the tolerance.

Note for developers: this migration **cannot be run from a local MAMP machine** as
currently configured — MAMP's PHP resolves to UTC while MySQL follows the host
(US/Pacific). Run it on test/prod, or align the two zones locally first.

## Required Actions After Deployment

### 4. Verify timestamps against the baseline

Using the values recorded in step 2, confirm each car renders identically now on the
admin car list (the only surface printing a time of day, so a sub-day shift is
visible there and nowhere else), the car details page, and the sitemap's `<lastmod>`.

A discrepancy means the conversion ran under a mismatched clock: **stop and restore
from the step 1 backup** rather than adjusting the display layer.

### Pre-deployment rehearsal (already performed)

This migration was rehearsed against a snapshot of production data (1,593 cars /
8,278 `cars_hist` rows) under production's actual `sql_mode`
(`NO_ENGINE_SUBSTITUTION`), with the session zone held constant:

| Check | Result |
| --- | --- |
| `ctime` / `mtime` / `last_verified` / `cars_hist.*` value drift | **0 rows changed** of 9,871 |
| Partial dates repaired | 13 `purchasedate` + 2 `solddate` |
| Rows backfilled | 1,505 active (0 sold touched) |
| New `cars_hist` rows from the backfill | 0 (`@disable_triggers` held) |
| `idx_cars_hist_timestamp` | intact |
| `owner_last_updated` NULLs remaining | 0 |

Note that production runs `NO_ENGINE_SUBSTITUTION`, not a strict `sql_mode`. The
partial-date repair is therefore a safeguard for strict-mode environments rather
than something production requires — it was verified to run correctly under both.

## User-Facing Changes

### New Features

- **Your name, email, website, and location now update on every car you own** ([#1873](https://github.com/elan-registry/registry/issues/1873)): Changing any of these in Account Settings previously updated your profile but left your car records showing whatever you entered when you first registered them — sometimes years out of date. Only your location was ever copied across, and only when it had map coordinates attached. All nine owner-contact fields (name, email, website, city, state, country, and coordinates) now propagate to every car you own on every edit path. The stale email address in particular mattered: it is the address the registry writes to when it asks you to confirm your car's details, so an address you corrected long ago could leave you silently unreachable. (If a car's address had already been recorded as undeliverable, correcting it here does not by itself put that car back into the verification queue — clearing that state on a confirmed email change ships in v2.30.2, alongside the bounce detection that sets it.)

### Improvements

- **Confirming an email change now syncs to your cars too** ([#1958](https://github.com/elan-registry/registry/issues/1958)): #1873 synced your contact fields to every car you own when you saved your profile — but changing your email specifically requires confirming a link sent to the new address, and that confirmation step wrote the new address to your account without triggering the same sync. Your cars kept the old email until you happened to save some other profile field. Confirming an email change now syncs it to your cars in the same step, closing the one path #1873 missed. Requires a one-time registration step per environment, documented in DEPLOYMENT.md.

- **Saving a car now refreshes its owner details from your profile** ([#1962](https://github.com/elan-registry/registry/issues/1962)): Editing a car previously re-saved whatever owner name, email, and location were already stored on that record, so a car could keep contact details you had corrected long ago — and saving the car again would not fix it. Opening a car and saving it now brings those eight fields up to date from your profile. This complements #1873, which propagates changes when you edit your profile; together they close the two paths by which a car's owner details could drift out of date. Your car's website is unchanged, as it remains a per-car field for now. Where an administrator or editor saves a car on someone else's behalf, the car keeps that member's details — never the staff member's.

- **Car list and registry searches no longer fail after a session expires** ([#1913](https://github.com/elan-registry/registry/issues/1913)): The car list, factory records list, car history, and statistics panels previously returned a "Could not load" error after a session timed out or the browser was restarted, because the CSRF token embedded in the page became stale. Public read-only endpoints now use rate limiting instead of token checks, eliminating the stale-token failure path entirely.

## Admin-Facing Changes

- **Owner sync now covers every contact field, and reports partial failures honestly** ([#1873](https://github.com/elan-registry/registry/issues/1873)): The owner panel's sync action now pushes all nine owner-contact fields rather than location alone, and no longer refuses to run for owners without map coordinates — eight of the nine fields never needed them. Where it previously reported only a count, it now names the cars it could not update, and an owner with no cars is reported as such instead of as an error. Each car's update and its audit-history entry are written in a single transaction, so a car can no longer be modified without its matching history row.

## Issues Resolved

- WIP: [#1752](https://github.com/elan-registry/registry/issues/1752) — tech-debt: decouple integration test suite from UserSpice's DB::getInstance() so CI can run the integration gate
- [#1867](https://github.com/elan-registry/registry/issues/1867) — bug: car merge ("duplicate"/"new owner" admin action) never moves userimages/ files to the surviving car
- [#1873](https://github.com/elan-registry/registry/issues/1873) — feat: sync all owner-contact fields (including email) to every car the owner has
- WIP: [#1876](https://github.com/elan-registry/registry/issues/1876) — docs: update the privacy policy for Brevo, bounce data, cross-car sync, and retention
- [#1953](https://github.com/elan-registry/registry/issues/1953) — bug: verification freshness test still falls back to cars.mtime — #1155's revision was never implemented
- [#1954](https://github.com/elan-registry/registry/issues/1954) — tech-debt: OwnerSyncResult conflates 'car no longer owned' with 'sync failed'
- [#1958](https://github.com/elan-registry/registry/issues/1958) — bug: confirmed email change via users/verify.php never syncs to cars.email
- WIP: [#1961](https://github.com/elan-registry/registry/issues/1961) — feat: reconciliation job to sync current owner information to the cars they own
- [#1962](https://github.com/elan-registry/registry/issues/1962) — bug: editing a car does not refresh the owner-contact columns from the profile
- [#1979](https://github.com/elan-registry/registry/issues/1979) — bug: Owner::syncOwnerFieldsToCars() can't distinguish a failed-to-load owner from zero owned cars
- WIP: [#1963](https://github.com/elan-registry/registry/issues/1963) — feat: make website owner-level only — remove the per-car website field
- [#1913](https://github.com/elan-registry/registry/issues/1913) — fix: cars-list DataTable never recovers from a stale/lost CSRF token
- [#1931](https://github.com/elan-registry/registry/issues/1931) — test: integration bootstrap does not define ELAN_IMAGE_DIR — CarTransferTest fails standalone
- [#1947](https://github.com/elan-registry/registry/pull/1947) — chore: refresh local dev data from production (developer tooling; no issue)
