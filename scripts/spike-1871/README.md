# Spike #1871 — Brevo Transactional Webhook Behaviour

Purpose: capture real Brevo webhook payloads on the test environment so the bounce-detection endpoint in
[#1887](https://github.com/elan-registry/registry/issues/1887) can be designed against observed behaviour rather than documentation. See
[#1871](https://github.com/elan-registry/registry/issues/1871). The server-side copies and the Brevo webhook are temporary and are deleted in
step 10; the fixture account stays on the test DB. This directory stays in the repo as the re-verification tool until #1887 ships its own
fixtures. Re-run this runbook end to end to re-verify Brevo's behaviour later. The user executes every
live step by hand; nothing here is automated.

## Prerequisites

- Brevo dashboard login with access to Transactional → Settings → Webhooks.
- SSH access to the test server via the host alias `a2hosting`.
- A known-good inbox you control (for the `delivered` fixture).
- A throwaway Outlook.com mailbox (Microsoft JMRP is the feedback loop Brevo consumes; see step 8).
- A working `.env` in the deployed test web root `/home/unibrain/test.elanregistry.org` — the send script reads the Brevo API key from the
  `plg_sendinblue` DB table at runtime and never prints it.

## Scripts

- `brevo-webhook-capture.php` — POST-only receiver. Requires `?k=<secret>`; wrong or missing key returns 404, non-POST returns 405. Appends one
  JSON line per call with `received_at`, `remote_addr`, `sapi`, `server_software`, `server_keys` (`HTTP_*`/`REDIRECT_*`/`CONTENT_*`/`PHP_AUTH_*`,
  auth and token values redacted to 4 chars plus length), `headers` (`getallheaders()`, same redaction), `raw_body`, `json_valid`, `body_is_list`,
  `event_count`. Always answers `200 {"ok":true}` (500 only if the append fails) so Brevo never retries during capture.
- `brevo-send-test.php` — CLI only. `--to=<addr>`, optional `--subject=…`, `--tag=…` (repeatable, default `verification-spike`), `--count=N`
  (max 10), `--env=<dir containing .env>` (default repo root), `--host=<db host>` (override `DB_HOST`; needed locally because PDO treats
  `localhost` as a Unix socket and ignores `DB_PORT` — use `--host=127.0.0.1` against MAMP). Sends through Brevo's REST API
  (`POST /v3/smtp/email` via `ext-curl`, no SDK) with `tags` and an `X-Spike: 1871` header and prints the `messageId` per send.
  `--list-webhooks` prints the account's transactional webhooks (`id`, `url`, `batched`, auth type, events) with any token redacted.
  `--list-blocked` prints Brevo's transactional suppression list (`GET /v3/smtp/blockedContacts`: email, `blockedAt`, reason code).

Note on placement: the send script resolves `vendor/autoload.php` (phpdotenv) from `dirname(__DIR__, 2)`, so on the server it must live
two levels below the deployed web root — hence `~/test.elanregistry.org/scripts/spike-1871/`. The deploy hook deletes `scripts/` on the
server, so that directory is normally absent; create it by hand in step 1 and remove it in step 10.

Note on Brevo's API IP allowlist: the account restricts API calls to authorised IPs. A run from an unlisted machine returns
`HTTP 401 … unrecognised IP address …` (observed 2026-09-03). Either run the script on the test server (already authorised, the
app sends mail from it) or add the developer IP under Brevo → Security → Authorised IPs first. With zero webhooks configured,
`--list-webhooks` prints `no transactional webhooks` (Brevo returns `400 document_not_found` for an empty list).

## Step 1 — Prepare and deploy the capture endpoint

Generate the secret and edit the two constants at the top of `brevo-webhook-capture.php` **before** copying it: replace `CAPTURE_SECRET`'s
placeholder `CHANGE-ME-32-HEX` and confirm `CAPTURE_FILE` is `/home/unibrain/spike-1871/capture.jsonl` — an absolute path
outside the web root. The secret **must be exactly 32 lowercase hex characters** (what `openssl rand -hex 16` produces): the
script 404s on every request unless `CAPTURE_SECRET` matches `^[0-9a-f]{32}$`, so the unchanged placeholder, an uppercase
paste, or a truncated value all fail closed.

Edit a temporary copy, not the tracked file, so the secret never lands in the working tree:

```bash
SECRET=$(openssl rand -hex 16); echo "$SECRET"
sed "s/CHANGE-ME-32-HEX/$SECRET/" scripts/spike-1871/brevo-webhook-capture.php > /tmp/capture.php
grep -n 'CAPTURE_SECRET\|CAPTURE_FILE' /tmp/capture.php
```

```bash
ssh a2hosting 'mkdir -p ~/spike-1871 ~/test.elanregistry.org/spike-1871 ~/test.elanregistry.org/scripts/spike-1871'
scp /tmp/capture.php a2hosting:~/test.elanregistry.org/spike-1871/capture.php
scp scripts/spike-1871/brevo-send-test.php a2hosting:~/test.elanregistry.org/scripts/spike-1871/
rm /tmp/capture.php
```

Verify the endpoint. The site may sit behind Cloudflare; the 405/404/200 statuses below are what the origin should produce.

```bash
curl -i https://test.elanregistry.org/spike-1871/capture.php
curl -i -X POST 'https://test.elanregistry.org/spike-1871/capture.php?k=wrong'
curl -i -X POST 'https://test.elanregistry.org/spike-1871/capture.php?k=<secret>' \
  -H 'Content-Type: application/json' -d '{"probe":"1871"}'
```

Expected: 405, then 404, then 200 with `{"ok":true}`. Confirm the third call also wrote a line — this doubles as the check that `CAPTURE_FILE` is
writable.

```bash
ssh a2hosting 'wc -l ~/spike-1871/capture.jsonl && tail -n1 ~/spike-1871/capture.jsonl'
```

## Step 2 — Create the Brevo webhook

In the Brevo UI: Transactional → Settings → Webhooks → Add a webhook.

- URL: `https://test.elanregistry.org/spike-1871/capture.php?k=<secret>`
- Events: `delivered`, `hardBounce`, `softBounce`, `blocked`, `invalid`, `spam`, `opened`
- Authentication: **Token** — keep the token in a local note only (it is throwaway but must not be committed anywhere).
- Description: `1871 spike — test env`

Record two UI facts while you are there: whether the form exposes a **header-name** field for Token auth, and whether it exposes a **batched**
toggle. The create-webhook API documents `auth:{type:"bearer",token}`, `batched:boolean` and custom `headers`, but the header name used for Token
auth is undocumented — capturing it is a primary goal of this spike.

## Step 3 — Confirm the webhook via the API

```bash
ssh a2hosting
cd ~/test.elanregistry.org/scripts/spike-1871
php brevo-send-test.php --env=/home/unibrain/test.elanregistry.org --list-webhooks
```

Record `batched` and the auth type for the new webhook in the second findings table, and note any mismatch with what the UI showed in step 2.

## Step 4 — Dry run A: known-good address (`delivered`)

```bash
php brevo-send-test.php --env=/home/unibrain/test.elanregistry.org --to=<known-good-address> --subject='1871 A delivered'
```

Note the `messageId`. Expect a `delivered` webhook. Do **not** open this mail in any client — step 9 depends on that.

## Step 5 — Dry run B: hard bounce (Mailtrap 550)

The Mailtrap bounce emulator is the primary bounce fixture for this spike. `inbox.mailtrap.io` has public MX records
(`10 inbox.mailtrap.io`, `50 fake.mailtrap.io`, checked 2026-09-03), so Brevo delivers over SMTP and receives the emulated response. The local part
must be lower-case.

```bash
php brevo-send-test.php --env=/home/unibrain/test.elanregistry.org \
  --to='bounce+550+no+such+user+here@inbox.mailtrap.io' --subject='1871 B hard'
```

Expected: `hard_bounce`. The open empirical question is whether Brevo attempts delivery (giving `hard_bounce` with a `reason` carrying the 550 text)
or pre-classifies the address (giving `blocked` or `invalid_email` instead). Record the `reason` field verbatim if present, and check
Brevo → Transactional → Logs for how Brevo itself classified the message alongside what the capture file received.

## Step 6 — Dry run C: soft bounce (Mailtrap 451)

```bash
php brevo-send-test.php --env=/home/unibrain/test.elanregistry.org \
  --to='bounce+451+mailbox+full@inbox.mailtrap.io' --subject='1871 C soft'
```

Expected: `soft_bounce` or a deferral. Record the retry cadence — how many webhook calls arrive, and the gaps between their `ts_event` values.

## Step 7 — Decision point

If B or C came back pre-classified (`blocked` / `invalid_email` rather than a bounce event), **stop**. Record exactly what arrived in both findings
tables and in Brevo Logs. No fallback fixture has been built for this spike; what to do next is decided from the recorded result.

## Step 8 — Spam / complaint (best effort)

Mailtrap cannot emulate complaints, and neither iCloud nor Gmail exposes a feedback loop Brevo consumes. Use the throwaway Outlook.com mailbox.

```bash
php brevo-send-test.php --env=/home/unibrain/test.elanregistry.org --to=<throwaway>@outlook.com --subject='1871 D spam'
```

Mark the message as Junk in the Outlook web UI, then wait up to 48 hours for a `spam` webhook. While waiting, check Brevo → Senders, Domains &
Dedicated IPs for complaint / FBL settings and record what is configured. If nothing arrives within 48 hours, record
"spam not observed; complaint path unverified" as an explicit risk for #1887 rather than leaving the row blank.

## Step 9 — Opens caveat

Do not open the step 4 mail in any client. If an `opened` event arrives anyway, that is Apple Mail Privacy Protection or Gmail image-proxy
prefetch, not a human open — record it as such. This is a single-message sample, so treat any conclusion about `opened` as indicative only.

## Step 10 — Read the capture, record findings, clean up

```bash
ssh a2hosting 'cat ~/spike-1871/capture.jsonl' | jq .
```

Token values in the capture are already redacted to 4 characters plus a length, so this output is safe to read and quote. Fill both findings tables
below. Pay particular attention to which `$_SERVER` key (if any) carries the auth header: `users/classes/Server.php` is upstream and unmodifiable,
it has no dedicated `HTTP_AUTHORIZATION` mapping (it falls through to plain string sanitisation), and `.htaccess` sets neither
`CGIPassAuth` nor `SetEnvIf Authorization`. If the auth header is absent from `$_SERVER` on A2, record that fact and whether
`getallheaders()` still exposes it — do **not** fix it here; #1887 owns the workaround.

Then clean up:

- Delete the webhook in Brevo → Transactional → Settings → Webhooks.
- Remove the server-side files.

```bash
# scripts/ is safe to remove wholesale: the deploy hook never ships it, so only spike files can exist there
ssh a2hosting 'rm -rf ~/spike-1871 ~/test.elanregistry.org/spike-1871 ~/test.elanregistry.org/scripts'
```

Finally, create one fixture car in the **test database only** — never production — whose owner email is whichever Mailtrap address actually bounced
(`bounce+550+no+such+user+here@inbox.mailtrap.io` if step 5 behaved as expected), with a chassis number clearly marked as a test fixture. Record its
id in the second findings table.

Copy the completed tables into `docs/development/EMAIL_SYSTEM.md` under a new section
**"Brevo Webhooks — Verified Behaviour (#1871)"**, then delete this directory.

## Findings — event payloads

The 2026-09-03 run's results live in `docs/development/EMAIL_SYSTEM.md` § "Brevo Webhooks — Verified Behaviour (#1871)"; the
tables below are blank templates for a re-run.

Context for the `observed event string` column: the docs give payload `event` values in snake_case (`hard_bounce`, `soft_bounce`, `invalid_email`,
`spam`, `blocked`, `delivered`) while the webhook-subscription enum is camelCase (`hardBounce`, `softBounce`, `invalid`, `spam`, `blocked`,
`delivered`, `opened`). The live payload decides. The docs also say `hard_bounce`/`soft_bounce` carry `event`, `email`, `message-id`, `ts_event`,
`reason` and `tags`, while `blocked`, `invalid_email` and `spam` document no `reason`.

| Event | Observed event string | Fields present | Tags present? | Reason present? | Notes |
| --- | --- | --- | --- | --- | --- |
| delivered | | | | | |
| hard_bounce | | | | | |
| soft_bounce | | | | | |
| blocked | | | | | |
| invalid_email | | | | | |
| spam | | | | | |
| opened | | | | | |

## Findings — transport and environment

| Question | Observed |
| --- | --- |
| Auth header name | |
| `$_SERVER` key | |
| `getallheaders()` needed? | |
| Batched | |
| Body shape (object / list) | |
| Brevo source IPs seen | |
| Mailtrap 550 classification | |
| Mailtrap 451 classification | |
| Spam observed? | |
| Fixture car id | |

## Out of scope

Everything below belongs to [#1887](https://github.com/elan-registry/registry/issues/1887), not this spike: the production bounce-detection
endpoint, the `email_events` table, any `Server.php` workaround, any `.htaccess` change to pass the auth header through, and a Brevo
source-IP allowlist.
