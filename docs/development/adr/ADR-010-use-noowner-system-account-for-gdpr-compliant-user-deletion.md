# ADR-010: Use noowner System Account for GDPR-Compliant User Deletion

## Status

**In Review** (retroactive)

## Date

Retroactive -- documented 2026-02-25

## Update (2026-08, #1553)

Several items from this ADR have since changed (resolutions, an architectural
removal, and behavior added after this ADR was written); the rest of the
document below describes the mechanism as it existed before this update and
is kept for historical context:

- **"No migration script creates noowner"** -- resolved, and literally so:
  `database/migrations/20260817035200_register_noowner_account.php` (a real
  Phinx migration, not a seed) creates the account on any provisioned
  environment, with `password = NULL` and `protected = 1` as this ADR
  specifies.
- **Email changed to `noowner@invalid`** (#1679) -- supersedes the
  `'noowner@example.com'` placeholder in the Account Specification below. The
  original value is syntactically valid, so it could be typed into
  `users/forgot_password.php` or `users/passwordless.php`, both of which
  locate an account by email lookup against submitted input. This closes the
  account-recovery vector that a NULL password alone does not cover -- the
  Security Model section below addresses login only. The two recovery forms
  are closed by different mechanisms, and the difference matters:
  - **Password reset is closed by validation.** `users/forgot_password.php`
    enforces `Validate`'s `valid_email` rule
    (`filter_var($value, FILTER_VALIDATE_EMAIL)`) before any lookup, and a
    bare-label domain fails it, so the address never reaches the query.
  - **Passwordless login is closed by delivery, not validation.**
    `users/passwordless.php` applies no server-side format check, so the
    address *does* match and *does* create a pending `us_email_logins` row.
    That row is inert only because `.invalid` is the RFC 2606 reserved TLD
    and cannot resolve: the vericode is stored hashed, never rendered to the
    page, and travels solely in an undeliverable email before expiring after
    15 minutes. The send failure does not invalidate the row early. This gate
    therefore rests entirely on the address being non-routable -- **never
    point this account at a routable address.** The missing validation is a
    framework gap tracked as #1687.
- **The migration is self-healing** (#1679) -- it forces `password`, `email`
  and `protected` to the locked-down values on a pre-existing account rather
  than only creating a missing one. Production's hand-created 2012 account had
  drifted (`protected = 0`, routable email) and would otherwise never have been
  corrected. `id`, `fname`, `lname` and `active` are left untouched so existing
  `cars.user_id` references survive.
- **`car_user` junction table** -- removed entirely (#1162,
  `database/migrations/20260711000000_drop_car_user_tables.php`). Ownership is
  now authoritative on `cars.user_id` alone; every reference to `car_user`
  below describes a mechanism that no longer exists.
- **Hard-coded ID 83 in the admin UI** -- still open, still real, tracked as
  #1562. The correct file is `app/admin/assets/admin-core.js`; every reference
  to `manage-consolidated.js` below is outdated.
- **"No transactional guarantees"** -- resolved (commit `36749ffe`, #609/#950;
  the transfer-request expiry below was later folded into the same
  transaction by `5158e02b`). The hook now wraps the profile delete,
  transfer-request expiry, and car reassignment in a single `CarRepository`
  transaction (`beginTransaction()`/`commit()`/`rollback()` in
  `after_user_deletion.php`), rolling back all three on any failure. The
  "P1: No transaction wrapping" row in Known Issues below and the "No
  transactional guarantees" / "Delete-then-reinsert on car_user"
  Negative-consequences bullets are stale.
- **Transfer-request expiry** -- added (commit `5158e02b`), not present when
  this ADR was documented. In the same transaction as steps 3 and 6 below,
  the hook also expires any `car_transfer_requests` row the deleted user
  initiated (`status IN ('pending', 'approved')` -> `'expired'`), preventing
  a dangling request that points at a deleted requester. Covered by
  `tests/integration/UserDeletionReassignmentTest.php`.

## Context

The Lotus Elan Registry maintains a historical database of Lotus Elan cars manufactured between 1963 and 1974. Car records have independent historical value
beyond any individual owner -- researchers, restorers, and enthusiasts rely on the registry to trace provenance, verify authenticity, and understand the
surviving population of these vehicles.

When a user exercises their GDPR Article 17 right to erasure (or when the system removes inactive/spam accounts via automated cleanup), the registry faces a
fundamental tension:

- **Privacy obligation**: The user's personal data (name, email, location, profile) must be deleted.
- **Data preservation obligation**: Car records represent community knowledge accumulated over two decades. Deleting cars alongside their owners would destroy
  irreplaceable historical data.
- **Referential integrity obligation**: The `cars`table has a`user_id` foreign key. Simply deleting the user creates orphaned records and application errors.

The problem is compounded by the denormalized schema (ADR-002), which copies owner PII (`fname`, `lname`, `email`, `city`, `state`, `country`, `lat`, `lon`,
`website`) into the `cars`table and, via triggers (ADR-003), into`cars_hist` rows.

## Decision

Use a dedicated **system user account** with username `noowner`(fname='No', lname='Owner', NULL password) as the reassignment target when a user is deleted. The
noowner account is a real row in the`users` table that cannot authenticate (NULL password hash) and is excluded from all automated cleanup processes.

### noowner Account Properties

| Property | Value | Rationale |
| --- | --- | --- |
| `username` | `'noowner'` | Lookup key used by deletion script |
| `fname` | `'No'` | Displays as "No Owner" in UI via`fname . ' ' . lname` |
| `lname` | `'Owner'` | See above |
| `password` | `NULL` | Prevents authentication; no login possible |
| `email` | `'noowner@invalid'` | Unroutable by construction (RFC 2606). Closes password reset by validation and passwordless login by delivery. Was `'noowner@example.com'` until #1679 |
| `protected` | `1` | Excludes the account from admin and automated account-deletion cleanup |
| `id` | `83` (production) | Assigned at creation in 2012; not guaranteed across environments |

### Mechanism

The deletion process is implemented as a UserSpice hook at `usersc/scripts/after_user_deletion.php`, called by the framework's `deleteUsers()`function
in`users/helpers/users.php`. The hook fires inside a `foreach`loop after the`users`row and`user_permission_matches` rows have already been deleted by UserSpice.

**Execution sequence per deleted user (variable `$id`):**

| Step | SQL Operation | Table(s) | Purpose |
| --- | --- | --- | --- |
| 1 | `SELECT id FROM users WHERE username = 'noowner'` | `users` | Dynamic lookup of noowner ID |
| 2 | `SELECT car_id FROM car_user WHERE userid = ?` | `car_user` | Enumerate cars owned by deleted user |
| 3 | `DELETE FROM profiles WHERE user_id = ?` | `profiles` | Remove PII (city, state, country, bio, etc.) |
| 4 | `DELETE FROM car_user WHERE userid = ?` | `car_user` | Remove old ownership junction records |
| 5 | `INSERT INTO car_user (userid, car_id) VALUES (?, ?)`(per car) | `car_user` | Create new junction records pointing to noowner |
| 6 | `UPDATE cars SET user_id = ? WHERE user_id = ?` | `cars` | Reassign primary ownership; triggers`cars_hist` row (ADR-003) |
| 7 | `logger(...)`with`LOG_CATEGORY_USER_DELETION` | `logs` | Audit trail entry |

**Fallback path**: If noowner does not exist, the script deletes `profiles`, deletes `car_user`rows, sets`cars.user_id = NULL`, and logs a warning.

### Database Schema

**`users` table** (noowner record):

- Standard UserSpice user record with NULL password
- `active = 1` (must remain active for FK integrity)
- `permissions = 0` (no admin or registry permissions)
- Located dynamically: `SELECT id FROM users WHERE username = 'noowner'`

**`profiles` table**:

- Deleted for the departing user (PII removal)
- noowner does not require a profile record

**`car_user` junction table**:

- Old rows (userid = deleted user) removed
- New rows (userid = noowner, car_id = each car) inserted

**`cars` table** (per ADR-002):

- `user_id` updated from deleted user ID to noowner ID
- Denormalized fields inherit noowner's data via trigger mechanism

**`cars_hist` table** (per ADR-003):

- New row inserted by `cars_after_update` trigger capturing the reassignment
- Pre-existing historical rows retain original owner's PII (known GDPR gap)

**`logs` table**:

- Audit entry with `LogCategories::LOG_CATEGORY_USER_DELETION = 'UserDeletion'`
- Records car count and noowner ID for traceability

### Integration Points

- **Admin UI** (`app/admin/assets/admin-core.js`): Provides a "No Owner" checkbox for
  manual car reassignment. The checkbox sends a `no_owner` flag; `app/admin/index.php`'s
  `reassign` handler resolves the account id server-side via `User::find('noowner')`. It
  previously hard-coded id 83 on the client — fixed in #1562, since a client-supplied id
  must never be trusted for this path and 83 was only correct by accident on production
- **Recovery script** (`FIX/_ARCHIVE/02-Cleanup-Orphaned-Profiles.php`): Reassigns orphaned cars (whose user_id points to a deleted user) back to noowner
- **Privacy policy** (`docs/faq/PRIVACY.md`): Explicitly documents the noowner pattern: "Car Ownership: Transferred to a system account called 'noowner'"
- **ElanRegistryOwner class**: No special handling -- treats noowner as a standard user record, which is intentional

### Security Model

- **Authentication**: noowner has NULL password hash -- cannot authenticate through any login path
- **Account recovery**: noowner's email is the deliberately unroutable sentinel
  `noowner@invalid`. Password reset is closed by validation — `users/forgot_password.php`
  requires the submitted address to clear `Validate`'s `valid_email` rule
  (`FILTER_VALIDATE_EMAIL`), which a bare-label domain fails. Passwordless login is closed
  by delivery instead: `users/passwordless.php` performs no server-side format check, so
  the address does match and a pending `us_email_logins` row is created, but the vericode
  is stored only as a hash, never rendered to the page, and transmitted solely in an email
  that `.invalid` (RFC 2606) guarantees cannot be delivered. The row expires unused after
  15 minutes. **The unroutable domain is therefore load-bearing, not cosmetic** — a
  routable address here would convert passwordless login into a live path to this account.
  Guarded by `tests/integration/database/RegisterNoownerAccountMigrationTest.php`; the
  underlying framework gap is tracked as #1687
- **Transfer interaction** (#1679): because `noowner@invalid` fails `FILTER_VALIDATE_EMAIL`
  by design, it must never be denormalized onto a car. `CarAdministrationService::transfer()`
  copies the target owner's contact fields onto `cars` and `car_history`, then validates
  them -- propagating the sentinel threw `CarValidationException` and rolled back the entire
  reassignment transaction, silently leaving the deleted owner's PII on their former cars.
  `transfer()` now blanks any owner email that fails the same filter rather than propagating
  or rejecting it. A car with no reachable owner correctly carries no owner email; contact
  flows resolve the owner through `user_id`, never through the denormalized `cars.email`
  copy. **Any future change to noowner's email must preserve both properties: unroutable,
  and blanked rather than propagated on transfer**
- **Authorization**: noowner has permissions level 0 -- no admin or registry access even if authentication were possible
- **Cleanup exclusion**: Explicitly excluded from all automated deletion queries by username check AND `protected` flag
- **Hook integrity**: The deletion hook fires for every `deleteUsers()` call, including admin panel deletions and cron cleanup -- no deletion path bypasses it
- **SQL safety**: All queries use prepared statements via the DB class
- **Audit trail**: Both application-level logging (`LogCategories`) and database-level triggers (`cars_hist`) record the reassignment

## Consequences

### Positive

- **Preserves irreplaceable historical data** -- car records survive user deletion; registry completeness maintained for community benefit
- **Maintains referential integrity** -- `cars.user_id`always points to a valid`users` row, preventing NULL FK issues, broken joins, and application errors
- **GDPR-compatible by design** -- personal data deleted; car records become pseudonymized, attributed to a system account with no real-world identity
- **Simple, auditable implementation** -- entire mechanism is a single ~48-line PHP script using standard prepared statements
- **Leverages existing framework hook** -- UserSpice's `after_user_deletion.php` hook requires no framework modification; fires automatically for all deletion
  paths
- **Enables recovery** -- orphaned car cleanup scripts can detect and repair broken ownership by reassigning to noowner
- **Full audit trail** -- database triggers (ADR-003) capture the ownership reassignment in `cars_hist`; application logging provides a second audit dimension
- **Transparent to end users** -- privacy policy explains the process plainly; users understand their PII is deleted while car data is preserved anonymously

### Negative

- **Does not scrub PII from historical records** -- `cars_hist` retains the deleted user's PII in pre-deletion snapshot rows; only prospective ownership points
  to noowner (most significant GDPR gap)
- **No transactional guarantees** -- the six SQL operations are not wrapped in a database transaction; mid-sequence failure leaves cars in inconsistent state
- **Hard-coded ID in admin JavaScript** -- `manage-consolidated.js` hard-codes noowner as ID 83 in three locations; breaks in non-production environments
- **No migration script creates noowner** -- the account is a manual setup requirement; new installations silently activate the fallback path
- **Magic string repeated across codebase** -- the username `'noowner'` appears as a bare string in at least 5 files with no centralized constant
- **Hook fires after user row deletion** -- by the time the hook executes, the user's PII is already gone from the `users`table; future PII scrubbing must
  use`cars.user_id`rather than joining to`users`
- **Delete-then-reinsert on car_user** -- between deletion and reinsertion, concurrent queries may see cars without junction records

### Risks

| Risk | Likelihood | Impact | Mitigation |
| --- | --- | --- | --- |
| GDPR data subject access request reveals PII in `cars_hist`after deletion | Medium | High | Implement PII scrubbing in`cars_hist` on deletion; document legitimate interest legal basis for historical retention |
| noowner account accidentally deleted by admin | Low | Critical | Set `protected = 1` flag; exclude from admin deletion UI; add startup validation check |
| Hard-coded ID 83 breaks in non-production environments | High (dev/staging) | Medium | Replace with API endpoint or PHP-rendered constant for dynamic lookup |
| Mid-deletion failure leaves orphaned car_user state | Low | Medium | Wrap deletion script in database transaction with rollback on failure |
| noowner missing in fresh installation; fallback sets NULL user_id | Medium (new installs) | Medium | Create FIX script to ensure noowner exists; add application startup check |
| Concurrent deletion of multiple users causes duplicate car_user rows | Very Low | Low | Add unique constraint on `car_user(userid, car_id)`; use `INSERT IGNORE` |
| Automated cleanup inadvertently targets noowner due to query change | Low | Critical | Unit test verifying noowner exclusion; set `protected` column as defense-in-depth |

## Alternatives Considered

### A. Hard-Delete Cars With User

Delete all car records when their owner is deleted, cascading through `cars`, `car_user`, and `cars_hist`.

**Rejected because:**

- Destroys irreplaceable data -- car records represent 20+ years of community knowledge
- Violates the registry's core mission to preserve knowledge of Lotus Elans
- No recovery path once car data is deleted
- GDPR requires deletion of *personal data*, not factual records about physical objects; disproportionate response

### B. Soft-Delete/Archive User Record, Retain Ownership Link

Mark the user record as "deleted" (`active = 0`, `deleted_at = NOW()`) but keep the row with PII intact. Cars continue pointing to the soft-deleted user.

**Rejected because:**

- GDPR non-compliant -- PII remains in the database, accessible to admins
- Retaining PII without legal basis is difficult to justify for a hobby car registry
- Increases data breach surface -- soft-deleted records are still in the database
- Every query touching users must filter for deleted status; missing a filter leaks data

### C. Anonymous Placeholder Without Real User Record

Use a sentinel value (`user_id = 0`or`user_id = -1`) instead of a real user record.

**Rejected because:**

- Breaks foreign key constraints unless FK checks are disabled
- Pervasive application changes -- every `users` join must handle the sentinel case
- Denormalization conflict (ADR-002) -- no user record to derive display values from
- Inconsistent with UserSpice patterns which assume user IDs reference valid rows
- Testing burden multiplied -- every feature must handle both real IDs and sentinel

### D. Tombstone Records With Synthetic IDs Per Deletion

Create a unique placeholder user per deletion (e.g., `username = 'deleted_12345'`), preserving one-to-one relationship between original owner and their cars.

**Rejected because:**

- Unbounded growth -- every deletion creates a new user record; `users` table accumulates tombstones
- Pseudo-PII risk -- synthetic IDs correlated with deletion logs may constitute pseudonymized data under GDPR
- No demonstrated need -- the registry does not need to distinguish "deleted user A's cars" from "deleted user B's cars"
- Same exclusion overhead as noowner but multiplied across potentially hundreds of records

## Known Issues and Future Improvements

| Priority | Issue | Recommendation |
| --- | --- | --- |
| P1 | PII retained in `cars_hist`after deletion | Add`UPDATE cars_hist SET fname='Deleted', lname='User', email=NULL, city=NULL, state=NULL, country=NULL, lat=NULL, lon=NULL, website=NULL WHERE user_id = ?` to deletion script |
| P1 | Hard-coded ID 83 in admin JS | Replace with PHP-rendered JavaScript variable or API endpoint for dynamic lookup |
| P1 | No transaction wrapping in deletion script | Wrap steps 2-6 in `START TRANSACTION`/`COMMIT`with`ROLLBACK` on failure |
| P2 | No migration creates noowner | Create FIX script to ensure noowner exists (idempotent) |
| P2 | No PHP constant for noowner username | Define `NOOWNER_USERNAME = 'noowner'` constant; update all references |
| P2 | noowner account not protected against admin deletion | Set `users.protected = 1`; verify admin UI respects flag |
| P3 | Hook fires after user row deletion | Document limitation; use `cars.user_id` for future PII scrubbing |
| P3 | Recovery script uses overly broad lookup | Change to `WHERE username = ?` only (remove fname/lname fallbacks) |

## References

> Most links below use a relative path baseline from when this ADR lived at
> `docs/adr/`, not its current `docs/development/adr/` home, and some target
> files have since moved or been removed entirely (`docs/faq/`, `FIX/`, and
> `manage-consolidated.js` no longer exist — see the Update block above for
> the latter). Only the "Tests" row has been corrected as part of #1445;
> repairing the rest needs per-row investigation of where each file went, out
> of scope here.

| Item | File |
| --- | --- |
| Deletion hook script | [/usersc/scripts/after_user_deletion.php](../../usersc/scripts/after_user_deletion.php) |
| UserSpice deleteUsers() | [/users/helpers/users.php](../../users/helpers/users.php) |
| Admin UI (No Owner checkbox) | [/app/admin/assets/manage-consolidated.js](../../app/admin/assets/manage-consolidated.js) |
| Orphaned car recovery | [/FIX/_ARCHIVE/02-Cleanup-Orphaned-Profiles.php](../../FIX/_ARCHIVE/02-Cleanup-Orphaned-Profiles.php) |
| Privacy policy | [/app/owner/privacy.php](../../app/owner/privacy.php) |
| LogCategories constant | [/usersc/classes/LogCategories.php](../../usersc/classes/LogCategories.php) |
| Owner class | [/usersc/classes/Owner.php](../../usersc/classes/Owner.php) |
| Database documentation | [/docs/development/DATABASE.md](../DATABASE.md) |
| Tests | [/tests/integration/UserDeletionReassignmentTest.php](../../../tests/integration/UserDeletionReassignmentTest.php) |
| Denormalization rationale | [ADR-002](ADR-002-denormalized-cars-table-cached-owner-data.md) |
| Audit trail triggers | [ADR-003](ADR-003-database-audit-trails-triggers-history-tables.md) |
| Car transfer workflow | [ADR-008](ADR-008-implement-self-service-car-ownership-transfer-workflow.md) |
