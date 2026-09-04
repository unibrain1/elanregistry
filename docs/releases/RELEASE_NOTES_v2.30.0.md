# Elan Registry v2.30.0 Release Notes

**Release Date:** September 3, 2026
**Type:** Minor Release - Car Verification System Foundation

## Required Actions After Deployment

1. This release's migration (`20260902104755`, adding the `owner_last_updated`
   column and related verification schema from #1155) is applied automatically
   by `scripts/server-hooks/post-receive` on `git push test|prod main` — it is
   the first live trigger rebuild run through Phinx on the hosting account, so
   confirm these before pushing:
   - **Pre-push:** take the `cars`/`cars_hist` backup described in
     [DEPLOYMENT.md](../development/DEPLOYMENT.md#database-migrations) — this
     backup, not `composer migrate:rollback`, is the real rollback path,
     because the migration's `down()` drops the new columns.
   - **Pre-push, on the target database:** confirm the deploy DB user has the
     `TRIGGER` privilege (`SHOW GRANTS FOR CURRENT_USER()`); if
     `SHOW VARIABLES LIKE 'log_bin'` is `ON`, `log_bin_trust_function_creators`
     must also be `ON` (or the user needs `SUPER`).
   - **Deploy `test` first**, then `prod`.
   - **Post-deploy:** `composer migrate:status` shows `20260902104755` applied,
     and `SHOW TRIGGERS LIKE 'cars'` returns 3 rows. If the migration aborts,
     fix the privileges above and re-run `composer migrate` — every step is
     idempotent, and the migration now fails loudly if a trigger is missing.
2. Cron transport (#1872) is already installed on test and prod as of
   2026-09-03: a cPanel cron requests `users/cron/cron.php` every 10 minutes
   on each host and `cron_ip` is pinned per environment. After deploying,
   confirm a `CronRequest` entry in Admin → Logs within the last 10 minutes.
   Optional tidy-up: change the installed crontab lines from `-s -k /dev/null`
   to `-fsS -o /dev/null` so cPanel stops mailing the response body every run
   (see `docs/development/DEPLOYMENT.md` § "Cron Transport").

## User-Facing Changes

None in this release. This is the first of six milestones building the Car
Verification System — its goal is that anyone researching a car can see how
fresh its record is, kept current with as little admin upkeep as possible.
This milestone lays the data-model and infrastructure foundation; no
verification email sends, new pages, or visible verification status appear
until v2.30.2 (Automatic Bounce and Delivery) and v2.30.4 (Freshness
Surfaced) land.

## Admin-Facing Changes

### Improvements

- **Verification system backend** ([#1155](https://github.com/elan-registry/registry/issues/1155)): A database migration and `CarVerificationManager` extensions, including `owner_last_updated` tracking — the shared freshness primitive every later Car Verification milestone builds on.
- **Brevo webhook behavior confirmed** ([#1871](https://github.com/elan-registry/registry/issues/1871)): Live spike against `test.elanregistry.org` settled the facts the bounce-detection endpoint (v2.30.2) depends on — events arrive one per POST (`batched: false`) with snake_case names, `tags` on every event, `reason` on bounces but not `spam`, Token auth as `Authorization: Bearer` reaching `$_SERVER['HTTP_AUTHORIZATION']` on A2 without `.htaccess` changes, and a suppressed address yields `blocked` on every send after its first `hard_bounce`. Documented in `docs/development/EMAIL_SYSTEM.md` § "Brevo Webhooks — Verified Behaviour"; reproducible tooling in `scripts/spike-1871/` (not deployed).
- **Cron transport installed** ([#1872](https://github.com/elan-registry/registry/issues/1872)): UserSpice's `cron.php` is now triggered every 10 minutes on test and prod (cPanel `curl`), with `cron_ip` pinned per environment and rejected requests visible in Admin → Logs. Neither host had a cron trigger before. The interval, allowlist semantics, per-environment table, and the contract cron jobs must honour (every active job runs on every hit, so jobs gate their own cadence) are documented in `docs/development/DEPLOYMENT.md` § "Cron Transport", with the dev launchd trigger in `ENVIRONMENT.md`. Unblocks the scheduled send and reconciliation jobs in v2.30.3.
- **EmailTemplate primitives added** ([#1874](https://github.com/elan-registry/registry/issues/1874)): Three missing primitives (highlighted row, button row, trusted-HTML row) added to the shared `EmailTemplate` class for later verification-email composition. `app/admin/design-system.php` now documents the highlighted row and its `--er-warning` token.

### Maintenance

- Completed one-time fix scripts `12-Trim-Cars-Column-Whitespace.php` and `13-Recover-Or-Clear-Lost-Car-Images.php` removed from `app/admin/scripts/fix/` (commit 4220be08, milestone housekeeping).

### Bug Fixes

- **Fixed plaintext-vericode bug** ([#1879](https://github.com/elan-registry/registry/issues/1879)): Email-change and password-reset flows on the
  project settings page, and owner account creation, now hash the vericode before storing it, matching the verifier's expected format.
- **Fixed transfer not clearing solddate** ([#1878](https://github.com/elan-registry/registry/issues/1878)): `CarAdministrationService::transfer()` now clears `solddate` on the `cars` row and on the transfer's `NEWOWNER` history row, so transferred cars are no longer silently excluded from verification eligibility. `transfer()` also clears `email_bounced` for real-owner transfers, since the flag belonged to the previous owner's address; `owner_last_updated` is deliberately not reset — a transfer is not owner re-attestation. Reassignment to the `noowner` system account (GDPR deletion, admin "no owner") is not a change of owner and keeps `solddate` as-is.

## Issues Resolved

- [#1155](https://github.com/elan-registry/registry/issues/1155) — feat: verification system backend — DB migrations, CarVerificationManager extensions, owner_last_updated tracking
- [#1871](https://github.com/elan-registry/registry/issues/1871) — Spike: verify Brevo webhook behavior against our design assumptions
- [#1872](https://github.com/elan-registry/registry/issues/1872) — chore: install & verify UserSpice's cron transport on test and prod
- [#1874](https://github.com/elan-registry/registry/issues/1874) — feat: add three missing primitives to EmailTemplate (highlighted row, button row, trusted-HTML row)
- [#1878](https://github.com/elan-registry/registry/issues/1878) — bug: CarAdministrationService::transfer() does not clear solddate on transfer
- [#1879](https://github.com/elan-registry/registry/issues/1879) — fix: email change from the project settings page stores a plaintext vericode the verifier no longer accepts
