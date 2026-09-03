# Issue #1871: Spike — verify Brevo webhook behavior against our design assumptions

**Branch:** `issue/1871-brevo-webhook-spike`
**Milestone:** `milestone/v2.30.0`
**Status:** Implemented — pending commit/PR

## Context

The bounce-detection design (FRD §10.2–10.3; issues #1887–#1890 in v2.30.2) assumes
things about Brevo's transactional webhooks that only a live delivery can settle:
the bounce/spam payload shapes, which header carries Token auth and under which
`$_SERVER` key it reaches PHP on A2 Hosting, whether our account delivers batched
events, and whether `tags` is present on every event type the endpoint filters on.
This spike produces those facts before any endpoint code is written. Output is a
verified-behaviour section in `docs/development/EMAIL_SYSTEM.md`, two small
reproducible scripts under `scripts/spike-1871/`, a bounce fixture in the test DB,
and a summary on #1871 / pointer on #1887.

Tier: Medium. PM agent skipped (issue is fully specified). Test-engineer consult
skipped (docs + dev-only tooling, nothing under test). No `senior-architect` here.

## What the docs already settle (checked 2026-09-03, cite in the write-up)

- Documented payload `event` values are **snake_case** (`hard_bounce`, `soft_bounce`,
  `invalid_email`, `spam`, `blocked`, `delivered`) while the subscription enum is
  camelCase (`hardBounce`…). The issue's "real names are camelCase" holds for the
  subscription side only. **Live payload decides** — the endpoint must not hardcode
  either until confirmed.
- `hard_bounce`/`soft_bounce` documented fields: `event`, `email`, `message-id`,
  `ts_event`, `reason`, `tags` (+ `id`). `blocked`/`invalid_email`/`spam`: `event`,
  `email`, `message-id`, `tags` — **no reason field documented**.
- Create-webhook API: `auth: {type: "bearer", token}`, `batched: boolean`
  (semantics undocumented), `headers: [{key,value}]` custom headers, IP allowlist
  ranges published on help.brevo.com. Header name for bearer **not documented**.
- **Mailtrap bounce emulator is the primary bounce fixture** (per the issue).
  `inbox.mailtrap.io` publishes a public MX (`10 inbox.mailtrap.io`, `50 fake.mailtrap.io`,
  checked 2026-09-03), so Brevo delivers to it over SMTP and receives the emulated
  response: `bounce+550+no+such+user+here@inbox.mailtrap.io` → hard bounce,
  `bounce+451+mailbox+full@inbox.mailtrap.io` (4xx) → soft bounce/deferred. Mailtrap's
  "does not work with API" caveat is about *Mailtrap's* sending API, not inbound MX.
  Open question the spike answers empirically: does Brevo attempt delivery (→ `hard_bounce`
  with `reason` carrying the 550 text) or pre-classify (→ `blocked`/`invalid_email`)?
  Local part must be lower-case, URL-encoded.

## Repo facts that shape the approach

- `users/classes/Server.php` is stock UserSpice 6.1.4 (unmodifiable); its `KEY_MAP`
  allowlist has no `HTTP_AUTHORIZATION`. Capture must read `$_SERVER` directly and
  `getallheaders()`; #1887 will need a project-owned header reader in `usersc/classes/`.
- `usersc/plugins/sendinblue/functions.php:82-88` builds `SendSmtpEmail` with no
  `tags`; plugin is upstream (unmodifiable). SDK is vendored at
  `usersc/plugins/sendinblue/vendor/` and `SendSmtpEmail::setTags()` exists (`:626`).
  The test-send script uses the SDK directly with the key from `plg_sendinblue`.
- API key lives in `plg_sendinblue` (test DB), not `.env`; DB creds are in `.env`
  (`DB_HOST/DB_PORT/DB_USER/DB_PASS/DB_NAME`), loaded by `users/init.php:54` via phpdotenv.
- Deploy hook deletes `scripts/` on the server — nothing under `scripts/spike-1871/`
  ships. User scp's the two files to test: capture under the web root (must be
  URL-reachable by Brevo), send script anywhere. Output JSONL goes **outside** the
  web root (`~/spike-1871/capture.jsonl`).
- No fallback fixture is built. If Mailtrap turns out to be pre-classified rather
  than attempted, that is a finding; what to do next is decided then.
- Existing `logs` categories `EmailBounced`/`SendinblueDebug` exist but are not
  needed: capture writes to a file, so **no LogCategories change**.

## Architecture & Design

### `scripts/spike-1871/brevo-webhook-capture.php` (throwaway, reproducible)

- POST-only; 405 otherwise. Requires `?k=<secret>` matching a constant set at the
  top of the file before scp (random 32 hex); 404 on mismatch so scanners see nothing.
- Appends one JSON line per call to `CAPTURE_FILE` (absolute path outside web root):
  `received_at`, `remote_addr`, `sapi` (`php_sapi_name()`), `server_software`,
  `server_keys` = every `$_SERVER` key starting `HTTP_`, `REDIRECT_`, `CONTENT_`,
  `PHP_AUTH_` with values (token value **redacted** to first 4 chars), `getallheaders()`
  (same redaction), `raw_body` verbatim, `json_valid`, `body_is_list` (batched detection),
  `event_count`.
- Always returns `200 {"ok":true}` (never make Brevo retry during capture).
- No UserSpice bootstrap; plain PHP, `declare(strict_types=1)`.

### `scripts/spike-1871/brevo-send-test.php` (CLI only)

- `PHP_SAPI !== 'cli'` → exit 1. Args: `--env=/path/to/.env` (default: repo root
  relative), `--to=<addr>`, `--subject=`, `--tag=` (repeatable; default
  `verification-spike`), `--count=1`.
- Loads `.env` with phpdotenv from the given dir, PDO to `plg_sendinblue` for
  `key/from/from_name/reply`, `require usersc/plugins/sendinblue/vendor/autoload.php`,
  sends via `TransactionalEmailsApi::sendTransacEmail` with `tags` and a
  `headers: ['X-Spike' => '1871']` marker; prints the returned `messageId`.
- Also `--list-webhooks`: GET `https://api.brevo.com/v3/webhooks?type=transactional`
  with the same key; prints `id,url,events,batched,auth.type` — settles the batched
  question from the account, not the docs.

### `scripts/spike-1871/README.md` — the runbook (user executes the live steps)

1. Generate secret; scp capture to `~/test.elanregistry.org/spike-1871/capture.php`
   (URL `https://test.elanregistry.org/spike-1871/capture.php?k=…`); create
   `~/spike-1871/`; edit `CAPTURE_FILE`.
2. Brevo UI → Transactional → Settings → Webhooks → Add: URL above, events
   `delivered, hardBounce, softBounce, blocked, invalid, spam, opened`, auth **Token**
   (record the token), description "1871 spike — test env". Note whether the UI
   exposes a header-name field or a "batched" toggle.
3. `--list-webhooks` → record `batched` for the new webhook.
4. Dry run A: `--to=<known-good>` → expect `delivered` (+ possibly `opened` later).
5. Dry run B (hard): `--to=bounce+550+no+such+user+here@inbox.mailtrap.io` → expect
   `hard_bounce`; record `reason` text and whether Brevo attempted delivery or
   returned `blocked`/`invalid_email` instead. Check Brevo → Transactional → Logs
   for the message to see its classification alongside the webhook.
6. Dry run C (soft): `--to=bounce+451+mailbox+full@inbox.mailtrap.io` → expect
   `soft_bounce` (and possibly `deferred`); record retry cadence observed.
7. If B/C were pre-classified (`blocked`/`invalid_email`) instead of attempted,
   stop and record it — no fallback is built in this spike.
8. Spam: create a throwaway Outlook.com mailbox (Microsoft JMRP is the complaint
   loop Brevo receives; iCloud and Gmail have no FBL, Mailtrap cannot emulate one).
   Send dry run D to it, mark the message as Junk in the Outlook web UI, wait ≤48 h
   for `spam` on the capture. Also check Brevo → Senders, Domains & Dedicated IPs for
   any complaint/FBL setting that must be on. If nothing arrives in 48 h, record
   "spam not observed; complaint path unverified" as an explicit risk line in the
   doc and on #1887 — do not block the spike on it.
9. Opens: after A, do **not** open in one client; note any `opened` that still arrives
   (Apple MPP / Gmail proxy prefetch). Small-sample caveat.
10. Read `capture.jsonl`; fill the findings table. Delete webhook, capture file, and
    the scp'd files when done; create the fixture car in the **test DB only** with
    whichever bounce address proved to bounce (Mailtrap `bounce+550…` expected;
    chassis clearly marked test) and record its id.

### `docs/development/EMAIL_SYSTEM.md` — new `## Brevo Webhooks — Verified Behaviour (#1871)`

Placed after "Verifying Email Delivery". Table per event (`delivered`, `hard_bounce`,
`soft_bounce`, `blocked`, `invalid_email`, `spam`, `opened`): exact `event` string,
fields present, `tags` present?, `reason` present?; auth header name + exact `$_SERVER`
key on A2 (and whether `getallheaders()` was needed); `batched` value and body shape
(object vs list); fixture addresses (Mailtrap hard/soft) + test car id, with Brevo's
observed classification; open-tracking caveat; spam result via Outlook.com/JMRP (or
"not observed within 48 h" + risk); which providers have no FBL (iCloud, Gmail) so
nobody retries this with the wrong mailbox; dated, with a
"re-verify with `scripts/spike-1871/`" note.
Cross-link from `docs/development/adr/ADR-012` "Consequences" (one line) only if the
findings contradict an ADR-012 statement — otherwise leave ADR-012 alone.

### Out of scope (goes to #1887)

Endpoint code, `email_events` writes, `Server.php` workaround, `.htaccess`
`CGIPassAuth`/`SetEnvIf` change (only *recorded* here if needed), IP allowlisting.

## Database & Security Considerations

- No schema change. One fixture car row in the **test** DB (never prod).
- Capture secret is per-spike, in the query string of a test-only URL, deleted after.
- Token values redacted in the capture file; file lives outside the web root; deleted after.
- Send script handles the live Brevo API key: reads it from the DB at runtime, never
  prints it, never writes it to disk.
- Recipient addresses in capture output are the user's own test addresses.

## UserSpice Integration

No framework function covers inbound webhooks or raw header access; `Server::get()`
is upstream. *(Correction 2026-09-03, architect review: `Server::get('HTTP_AUTHORIZATION')`
returns the value intact — `KEY_MAP` is a sanitiser table with a string fall-through, not an
allowlist. Verified via CLI; #1887 needs no project-owned header reader.)* Nothing duplicated; the spike deliberately avoids
UserSpice bootstrap in both scripts.

## Implementation Checklist

- [x] `scripts/spike-1871/brevo-webhook-capture.php` per design (parallel-safe) — done 2026-09-03;
      secret guard is a `^[0-9a-f]{32}$` shape check (PHPStan constant-folds a const equality); phpstan + phpcs clean
- [x] `scripts/spike-1871/brevo-send-test.php` per design incl. `--list-webhooks` (parallel-safe) — done 2026-09-03;
      deviation: calls Brevo REST via `ext-curl` instead of the plugin-vendored SDK (that `vendor/` is gitignored, absent on CI,
      so PHPStan could never resolve it); added `--host` (PDO treats `localhost` as a socket and ignores `DB_PORT`)
- [x] `scripts/spike-1871/README.md` runbook with the 10 steps + findings-table template (parallel-safe) — done 2026-09-03; markdownlint 0 issues
- [x] Local smoke: run capture under `php -S` and POST a sample `hard_bounce` body with
      `Authorization: Bearer x`; confirm JSONL line, redaction, `body_is_list` on a
      `[...]` body (depends on: capture) — done 2026-09-03: 405/404/200/200, 2 JSONL lines,
      `HTTP_AUTHORIZATION` → `Bear…(len=26)`, list body `body_is_list:true event_count:2`
- [x] Local smoke: `brevo-send-test.php --list-webhooks` against dev DB key (read-only API call) (depends on: send script)
      — 2026-09-03: first run hit `HTTP 401 unrecognised IP address` (account API IP allowlist); after the dev IP was
      authorised the call succeeded: `no transactional webhooks` (Brevo returns `400 document_not_found` for an empty list —
      script now maps that to the empty-list message). `batched` is read in live step 3 once the webhook exists
- [x] `vendor/bin/phpstan analyse scripts/spike-1871/*.php` and `vendor/bin/phpcs` clean (depends on: both scripts)
      — 2026-09-03: phpstan `[OK] No errors`; `check-coding-standards.php scripts/spike-1871` 0 errors 0 warnings
- [x] **User-executed live steps 1–10** per README; capture.jsonl + Brevo UI observations handed back (depends on: README)
      — step 1 done 2026-09-03: 405/404/200 from origin via Cloudflare; `sapi=litespeed`, `REMOTE_ADDR` is the real client IP
      (host restores `CF-Connecting-IP`); curl probe with `Authorization: Bearer …` arrives as `$_SERVER['HTTP_AUTHORIZATION']`
      and in `getallheaders()` — no `.htaccess` change needed; `X-Mailin-Custom` → `HTTP_X_MAILIN_CUSTOM`
      — steps 2–3 done 2026-09-03: webhook created in the new Plugins & Integrations UI (3-step form: Name / Endpoint / Events;
      no header-name field, no batched toggle, per-event "Send test request"; all 15 events on by default);
      `GET /v3/webhooks?type=transactional` → `batched:false`, `auth.type:"bearer"` (UI calls it Token), UI Name ≠ API
      `description`; enum = `opened,uniqueOpened,unsubscribed,proxyOpen,softBounce,delivered,blocked,spam,request,error,
      uniqueProxyOpen,hardBounce,invalid,deferred,click`. First four sends went out before the webhook was saved (events lost,
      Brevo does not replay); re-sent after activation
      — step 8: Outlook.com auto-junked the first (pre-webhook) message on arrival (reputation signal); second message moved
      to Inbox and reported as Junk at 2026-09-03 13:39 PDT (20:39 UTC) — `spam` window runs to 2026-09-05 20:39 UTC
      — steps 4–9 done 2026-09-03: 31 capture records; live `delivered`, `request`, `hard_bounce` (fresh Mailtrap local part,
      `reason: 550 …`), `soft_bounce` (`451 mailbox full`), `blocked` (suppressed address), `spam` (~10 s after junk report),
      `unique_opened` (Apple Mail / Outlook web); `Authorization: Bearer` → `HTTP_AUTHORIZATION`; `batched:false`, object bodies;
      Brevo Logs export cross-checked one-for-one. Archived in `../Plans/spikes/1871-brevo-webhook/`. Step 10 (fixture ids,
      cleanup) done 2026-09-03: user 93 / car 15 on test DB; webhook 2167682 deleted (`--list-webhooks` → none); server dirs removed
- [x] `docs/development/EMAIL_SYSTEM.md` new section populated from real captures — no placeholder rows (depends on: live steps)
      — done 2026-09-03; fixture user 93 / car 15 (test DB) recorded; markdownlint + `check:docs` clean
- [x] Post findings summary on #1871; comment on #1887 linking the section and listing
      design deltas (event-name case, header key, batched, spam observability)
      (depends on: doc section) — done 2026-09-03: issues/1871#issuecomment-5532003148, issues/1887#issuecomment-5532003351
- [x] `composer check:docs` clean; PHPStan baseline hygiene (no touched PHP in baseline) — 2026-09-03:
      `Documentation checks passed.`; no `scripts/spike-1871` entries in `phpstan-baseline.neon`
- [x] Run `senior-architect` review of the diff, address findings — 2026-09-03: 2 Blocking (false `Server::get()`
      allowlist claim → verified via CLI and corrected in doc/README/plan; dangling fixture-id sentence → filled/removed at
      step 10), recommendations applied (redact `QUERY_STRING`/`REQUEST_URI`/`REDIRECT_URL`, `substr` not `mb_substr`,
      explicit TLS verify + connect timeout, hedged `REMOTE_ADDR`/source-IP claims, README tables marked as re-run
      templates, spike dir deliberately not listed in `scripts/README.md`); `event_count` semantics kept as designed

## Verification

- Local: capture smoke via `php -S 127.0.0.1:8089 -t scripts/spike-1871` + `curl -X POST …`.
- Live: `capture.jsonl` contains ≥1 line each for `delivered`, `hard_bounce`
  (Mailtrap 550) and `soft_bounce` (Mailtrap 451), with the auth header visible
  under a named `$_SERVER` key (or explicitly absent, with the
  `.htaccess`/`CGIPassAuth` fix recorded).
- Acceptance criteria on #1871 each map to a row in the new doc section.
