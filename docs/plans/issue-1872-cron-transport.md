# Issue #1872: chore: install & verify UserSpice's cron transport on test and prod

**Branch:** `issue/1872-cron-transport`
**Milestone:** `milestone/v2.30.0`
**Status:** Implemented — pending commit/PR

## Context

The live work is done. On 2026-09-03 the user added two cPanel cron lines on the
A2 account (both hosts share one account; `test.elanregistry.org` is a
subdirectory), each hitting `users/cron/cron.php` every 10 minutes via `curl`,
set `cron_ip` deliberately on each environment, confirmed `crons_logs` rows on
both, and confirmed a rejected request from an unexpected IP is logged. Neither
host had cron running before. All six acceptance criteria are met on the issue.

What remains is the repo side: nothing in `docs/`, the wiki, or `CLAUDE.md`
describes the cron transport on any environment, and the dev machine's own
trigger (a launchd job on this Mac, running since 2026-08-05) is undocumented.
v2.30.3's `send_verification_batch` and reconciliation jobs will be written
against this transport, so the interval and allowlist semantics must be
recorded where a developer will find them.

Tier: Small. Docs-only PR — no application code, no tests, no migrations.
PM, test-engineer, and security review skipped. Release-notes entry already
exists (written by `/start-milestone`); `/execute-plan` Step 9 keeps it current.

## Verified facts to record (from source, not the issue text)

`users/cron/cron.php` (upstream, unmodifiable):

- Logs every hit as `CronRequest` ("Cron request from $ip.") before any check.
- Allowlist: if `cron_ip` is non-empty, the request is denied (logged as
  "Cron request DENIED from $ip.", then `die`) unless `$ip == cron_ip` or
  `$ip == '127.0.0.1'`. An empty `cron_ip` allows everyone. The `off` sentinel
  is therefore "loopback only". `$ip` comes from `ipCheck()` →
  `Server::get('REMOTE_ADDR')` only (no `X-Forwarded-For`), so behind
  Cloudflare it is the real client IP and cannot be spoofed by header.
- No schedule concept: every active row in `crons` runs, in `sort` order, on
  every hit, and one `crons_logs` row is inserted per job per hit whether or
  not the job did anything. Jobs own their own cadence.
- Runs as `user_id = 1` when unauthenticated (the `crons_logs.user_id` value).
- Because the cPanel `curl` targets the public hostname, the request leaves the
  box, passes through Cloudflare, and arrives with the server's public outbound
  IP as `REMOTE_ADDR`, not `127.0.0.1`. `cron_ip` on test/prod is therefore a
  literal IP, which the doc describes as "the server's public outbound IP as
  shown in the first `CronRequest` log entry" — the literal address is not
  published.

Dev machine (macOS, MAMP):

- `~/Library/LaunchAgents/org.elanregistry.local-cron.plist`, `StartInterval`
  600, runs `curl -s -o /dev/null -w '%{http_code}'` against
  `http://localhost:9999/ElanRegistry/Registry/users/cron/cron.php`, appends
  `date status=NNN` to `~/Library/Logs/ElanRegistry/local-cron.log`.
- Dev `settings.cron_ip` is `::1` (IPv6 loopback — `localhost` resolves to
  `::1` on macOS, and `127.0.0.1` is the only hard-coded bypass, so `::1` must be
  the configured value).
- One active job in `crons`: "Test Job" (id 3). Rows every 10 minutes since
  2026-08-05.

Server crontab as installed (user's comment on #1872):

```text
*/10 * * * * /usr/bin/curl -s -k /dev/null https://test.elanregistry.org/users/cron/cron.php
*/10 * * * * /usr/bin/curl -s -k /dev/null https://elanregistry.org/users/cron/cron.php
```

Known defect in those lines, not yet fixed: `/dev/null` lacks `-o`, so curl
treats it as a second URL (fails silently) and prints the cron.php body to
stdout, which cPanel mails each run. `-k` is unnecessary. The doc records the
**recommended** form and notes the fix is pending on the server:

```text
*/10 * * * * /usr/bin/curl -fsS -o /dev/null https://elanregistry.org/users/cron/cron.php
```

## UserSpice Integration

Entirely UserSpice: Cron Manager (Admin → Settings → Cron Manager), the
`crons`/`crons_logs` tables, `settings.cron_ip`, `users/cron/cron.php`. Nothing
custom is added; the project only documents how the transport is wired per
environment. `LogCategories::LOG_CATEGORY_CRON_REQUEST` already exists.

## Database & Security Considerations

- No schema change, no code change.
- The doc must not publish the server's outbound IP, the cPanel account name,
  or home-network IPs. Describe values, do not paste them.
- `REMOTE_ADDR`-only allowlisting is a security property worth stating so a
  future change to `ipCheck()` or the Cloudflare setup is recognised as
  security-relevant.

## Architecture & Design

Three doc touches, one owner per topic (per the workspace rule "one document
owns each topic"):

1. **`docs/development/DEPLOYMENT.md`** — new `### Cron Transport (UserSpice
   Cron Manager)` subsection under `## ✅ Post-Deployment Configuration
   Requirements`, after "UserSpice Page Permissions". This is the owner of
   the per-environment facts. Contents:
   - What `cron.php` does and does not do (no schedule; every active job on
     every hit; jobs own cadence; `crons_logs` row per job per hit).
   - Allowlist semantics table: `cron_ip` empty / `off` / literal IP, with the
     `127.0.0.1` bypass and the `REMOTE_ADDR`-only note.
   - Per-environment table: dev / test / prod — trigger mechanism, interval
     (10 min everywhere), `cron_ip` policy (dev `::1`; test and prod: server's
     public outbound IP as seen in `CronRequest`), where to see evidence
     (Admin → Logs category `CronRequest`; Admin → Settings → Cron Manager
     for `crons_logs`).
   - **Contract for cron job authors** — a prominent callout, first thing in
     the subsection, because v2.30.3's jobs are written against it:
     - The transport fires **every 10 minutes** on dev, test, and prod
       (`*/10 * * * *`). Every active job runs on every hit: a job runs
       ~144 times a day whether it has work or not.
     - A job therefore must be idempotent and gate its own cadence (e.g. a
       "last ran" timestamp in `settings` or its own table, or a "due" query
       that returns nothing when there is nothing to do). Never assume daily,
       never assume hourly.
     - Do not use `crons_logs` to infer how often real work happened — it
       records every hit. Log real work under the job's own log category.
     - Assume the interval can change (it is a cPanel setting, not code):
       jobs must be correct at any hit frequency. If a job needs the
       interval as a number, read it from one place — this section — and
       treat it as an upper bound on latency, not a guarantee.
     - Runtime budget: a job must finish well inside 10 minutes or it will
       overlap its own next run.
   - Recommended crontab line and the note that the installed lines still
     carry the `-o` typo.
   - Verification steps (the same three the issue used: `crons_logs` row
     appears unattended; a hit from a foreign IP logs DENIED; interval
     recorded).
   - History line: first installed 2026-09-03 (#1872); neither host had cron
     before.
   - Add a checklist item to "Deployment Verification Checklist": cron still
     firing after deploy (the deploy hook must not disturb it, but the
     checklist is where operators look).
2. **`docs/development/ENVIRONMENT.md`** — new step 6 under `### Development
   Setup`: "Local cron trigger (optional)". Describes the launchd job by
   behaviour (label, 600 s interval, curl target, log path) and gives the
   `launchctl load` command; tells the reader to set `cron_ip` to `::1` in
   Admin → Settings. Links to DEPLOYMENT.md for semantics rather than
   repeating them. Per user decision, the plist file itself is **not**
   committed.
3. **`docs/development/SYSTEM_OVERVIEW.md`** — one sentence in §4 after
   "Verification is what keeps the data true — and it is not running": the
   scheduled-job transport now exists on every environment (#1872), so the
   missing piece is the jobs, not the scheduler. Keeps §4/§6 from implying
   there is no scheduler when v2.30.3 lands.

Not touched: wiki (no page mentions cron; installation-from-zero docs can link
to DEPLOYMENT.md later if needed), `CLAUDE.md` (no new command), ADRs.

## Implementation Checklist

- [x] Add `### Cron Transport (UserSpice Cron Manager)` subsection and the
      verification-checklist item — `docs/development/DEPLOYMENT.md`
      (parallel-safe)
- [x] Add "Local cron trigger (optional)" step 6 under Development Setup —
      `docs/development/ENVIRONMENT.md` (parallel-safe)
- [x] Add one-sentence scheduler note to §4 — `docs/development/SYSTEM_OVERVIEW.md`
      (parallel-safe)
- [x] Add a "Writing a cron job" row under `## Key Patterns (Quick Summary)`
      pointing at the DEPLOYMENT.md contract (10-minute interval, self-gating
      cadence) — `docs/development/QUICK_REFERENCE.md` (parallel-safe)
- [x] `npx markdownlint` on the four files (line-length 160) and
      `composer check:docs` clean (depends on: all four edits)
- [x] Refresh the #1872 entry in `docs/releases/RELEASE_NOTES_v2.30.0.md`
      (Admin-Facing Improvements + Required Actions item 2) with the real
      interval and the doc pointer (depends on: DEPLOYMENT.md)
- [x] Post a closing comment on #1872 linking the DEPLOYMENT.md section and
      noting the pending crontab `-o` fix (depends on: DEPLOYMENT.md)
- [x] Run `senior-architect` review of the diff (docs accuracy against
      `users/cron/cron.php`), address findings

No PHPStan baseline item: no PHP touched. No `/security-review`: no
forms/SQL/auth code.

## Verification

- `composer check:docs` passes.
- `npx markdownlint` clean on DEPLOYMENT.md, ENVIRONMENT.md, SYSTEM_OVERVIEW.md, QUICK_REFERENCE.md.
- Every factual claim in the new DEPLOYMENT.md section maps to a line in
  `users/cron/cron.php` or `users/helpers/us_helpers.php::ipCheck()` (architect
  review checks this).
- Acceptance criteria on #1872 are all already satisfied live; the PR closes
  the documentation gap only.
