# Elan Registry v2.30.0 Release Notes

**Release Date:** September 2, 2026
**Type:** Minor Release - Car Verification System Foundation

## Required Actions After Deployment

1. Run pending database migrations (`composer migrate`) — adds the
   `owner_last_updated` tracking column and related verification schema
   from #1155.
2. Install and verify UserSpice's cron transport on test and prod per #1872's
   acceptance criteria before any later Car Verification milestone depends on
   scheduled jobs running.

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

- **Verification system backend** ([#1155](https://github.com/elan-registry/registry/issues/1155)): Database migrations and `CarVerificationManager` extensions, including `owner_last_updated` tracking — the shared freshness primitive every later Car Verification milestone builds on.
- **Brevo webhook behavior confirmed** ([#1871](https://github.com/elan-registry/registry/issues/1871)): Spike verifying Brevo's webhook design assumptions ahead of the bounce-detection work in v2.30.2.
- **Cron transport installed** ([#1872](https://github.com/elan-registry/registry/issues/1872)): UserSpice's cron transport installed and verified on test and prod, unblocking the scheduled send and reconciliation jobs later in the arc.
- **EmailTemplate primitives added** ([#1874](https://github.com/elan-registry/registry/issues/1874)): Three missing primitives (highlighted row, button row, trusted-HTML row) added to the shared `EmailTemplate` class for later verification-email composition.

### Bug Fixes

- **Fixed plaintext-vericode bug** ([#1879](https://github.com/elan-registry/registry/issues/1879)): Email change from the project settings page no longer stores a vericode in a format the verifier rejects.
- **Fixed transfer not clearing solddate** ([#1878](https://github.com/elan-registry/registry/issues/1878)): `CarAdministrationService::transfer()` now clears `solddate` on transfer, so transferred cars are no longer silently excluded from verification eligibility.

## Issues Resolved

- [#1155](https://github.com/elan-registry/registry/issues/1155) — feat: verification system backend — DB migrations, CarVerificationManager extensions, owner_last_updated tracking
- WIP: [#1871](https://github.com/elan-registry/registry/issues/1871) — Spike: verify Brevo webhook behavior against our design assumptions
- WIP: [#1872](https://github.com/elan-registry/registry/issues/1872) — chore: install & verify UserSpice's cron transport on test and prod
- [#1874](https://github.com/elan-registry/registry/issues/1874) — feat: add three missing primitives to EmailTemplate (highlighted row, button row, trusted-HTML row)
- WIP: [#1878](https://github.com/elan-registry/registry/issues/1878) — bug: CarAdministrationService::transfer() does not clear solddate on transfer
- WIP: [#1879](https://github.com/elan-registry/registry/issues/1879) — fix: email change from the project settings page stores a plaintext vericode the verifier no longer accepts
