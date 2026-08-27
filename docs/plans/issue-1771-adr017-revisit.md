# Issue #1771: revisit ADR-017 vendoring decision now that Node is available on prod

**Branch:** `tech-debt/1771-adr017-revisit`
**Milestone:** `milestone/v2.29.4`
**Status:** Implemented — pending commit/PR

**Outcome:** verification succeeded — Node/npm resolve non-interactively on
`test.elanregistry.org` with no workaround needed, and a real `npm ci &&
npm run build` measured ~16s + <1s. **ADR-018 written, superseding
ADR-017.** Follow-up implementation issue filed: #1806.

## Context

ADR-015 (2026-04-27) rejected a build-at-deploy pipeline for vendored
frontend assets, reasoning that "shared hosting does not natively support"
a build step at deploy time. ADR-017 (2026-08-22, current, Accepted)
replaced ADR-015's hand-copy workflow with build-then-commit (`npm run
build` regenerates `usersc/js`/`usersc/css` from `node_modules`, output is
committed, CI's `vendor-drift` job catches drift) — but ADR-017 explicitly
re-affirmed ADR-015's build-at-deploy rejection, on the stated premise that
"the deploy hook ... will continue to never invoke `npm install` or `npm
run build`."

That premise is now in question: `which node` on the A2 host reportedly
resolves via nvm, which ADR-015/017 never verified non-interactively (nvm
typically needs RC-file sourcing that a non-interactive git-hook shell
skips). Meanwhile the cost of the current build-then-commit approach is
real and recurring: a routine Dependabot version bump to `package.json`
correctly fails CI's `vendor-drift` job because nothing regenerates the
committed output — **confirmed live on PR #1739** (`maplibre-gl` 6.4.1 →
6.5.0 bump), which is currently blocked pending a manual rebuild-and-push.
The same pattern recurred for #1740, and already happened three times
before (#1741, #1742, #1743).

This issue is not a proposal to switch to build-at-deploy — it's a
proposal to verify the constraint that killed that option twice before
(ADR-015, then re-affirmed in ADR-017), using facts, and record a real
decision either way.

## UserSpice Integration

Not applicable — this is a build-tooling/deployment-process decision, no
UserSpice framework surface involved.

## Database & Security Considerations

- No schema changes.
- **Deploy-atomicity risk** (a new consideration neither ADR-015 nor
  ADR-017 addressed): confirmed via this session's research that
  `scripts/server-hooks/post-receive` does a blocking `git checkout
  "$newrev" --force` directly into the live served document root — no
  maintenance-mode gate, no release-directory/symlink swap. Composer
  install and Phinx migrations already run synchronously in this exposed
  window today; adding a build step widens it, and JS/CSS build output
  regenerates as many small files (confirmed: `scripts/build.js` does ~34
  file-level esbuild/copy/concat operations), so a live request mid-build
  is more likely to hit a broken page than today's brief PHP-swap
  inconsistency.
- **Supply-chain exposure**: build-at-deploy means `npm ci` fetches from
  the npm registry directly on the production host, under the same OS
  user that owns the live site and `.env`, on every push — a materially
  larger blast radius than today's CI-only fetch + reviewable committed
  artifact. Any resulting ADR must record this tradeoff was weighed, not
  just the atomicity risk.
- **`.deployignore` conflict** (confirmed via this session's research):
  `package.json`, `package-lock.json`, and `scripts/` (containing
  `build.js` itself) are all deleted post-checkout, currently *after* the
  Composer/migration window — a build-at-deploy step would need all three
  present, requiring cleanup order to change if that path is chosen.

## Architecture & Design

This issue's actual output is a decision, recorded as a new/updated ADR —
not application code. The one blocking prerequisite this plan can't
complete unassisted: **verifying Node/npm behavior non-interactively on
the actual deploy host**, which requires SSH access this environment
doesn't have. User has confirmed they will run this verification
personally and report results back.

**Verification commands to run on `test.elanregistry.org`** (via SSH,
matching the git hook's actual non-interactive exec context — i.e. run
these as a script/one-liner, not from an interactive login shell, since an
interactive shell sources RC files a git hook's exec context does not):

```bash
# 1. Confirm node/npm resolve at all in a non-interactive context
ssh test.elanregistry.org 'bash -c "which node; which npm; node --version; npm --version"'

# 2. If step 1 fails (nvm not sourced), try common nvm non-interactive patterns
ssh test.elanregistry.org 'bash -c "export NVM_DIR=\$HOME/.nvm; [ -s \$NVM_DIR/nvm.sh ] && . \$NVM_DIR/nvm.sh; node --version"'

# 3. Time and memory-profile a real build in the repo's actual working tree
#    on the host (adjust path to the actual deploy WORK_TREE)
ssh test.elanregistry.org 'cd /home/unibrain/test.elanregistry.org && \
  export NVM_DIR=$HOME/.nvm; [ -s $NVM_DIR/nvm.sh ] && . $NVM_DIR/nvm.sh; \
  /usr/bin/time -v npm ci 2>&1 | tail -20 && \
  /usr/bin/time -v npm run build 2>&1 | tail -20'
```

Report back: (a) whether node/npm resolve non-interactively at all — this
alone may kill the option before timing matters, (b) if they do resolve,
the wall-clock time and peak memory for `npm ci && npm run build`, (c) any
error output.

**Decision framework** (both branches fully specified so the ADR can be
written immediately once verification results are in):

- **If Node/npm do NOT resolve non-interactively**, or resolve but
  `npm ci && npm run build` is unacceptably slow/memory-heavy for the
  shared host: **reaffirm ADR-017** with this new evidence recorded, and
  separately implement the "GitHub Actions rebuild-and-push-back" fallback
  already named in the issue body (a workflow that runs `npm run build`
  and pushes a commit onto the Dependabot PR branch, needing
  `contents: write` on `GITHUB_TOKEN` for that specific workflow — already
  confirmed acceptable per the issue). This fallback closes the actual
  recurring pain (#1741-1743 pattern) without touching the deploy path at
  all.
- **If Node/npm resolve non-interactively and the build cost is
  acceptable**: **write ADR-018, superseding ADR-017**, covering:
  process — `usersc/js`/`usersc/css` become gitignored build output
  (removed from git entirely); `post-receive` gains an `npm ci && npm run
  build` step inserted between the Composer/migration window and the
  `.deployignore` cleanup (which must be reordered — `package.json`,
  `package-lock.json`, and `scripts/` currently get deleted, and would
  need to survive until after the new build step runs, then still be
  deleted); CI's `vendor-drift` job is retired; explicit failure/halt
  semantics for the new build step (matching how Composer/migration
  failures already hard-stop the hook today — halt-and-alarm, not silent
  partial deploy); `docs/development/DEPLOYMENT.md` and the Wiki's
  `Registry-Installation-Production.md` updated to document the Node/nvm
  runtime requirement on the deploy host.

Either path must unblock #1739 and #1740 (the two currently-blocked
Dependabot PRs) and prevent recurrence of the #1741-1743 pattern.

## Out of Scope

- Assuming build-at-deploy is the outcome before verification — if the
  non-interactive check fails or the cost is unacceptable, reaffirming
  ADR-017 + building the Actions fallback is the correct closure, not a
  failure of this issue.
- Changing which packages are vendored vs. CDN-loaded — that's ADR-017's
  own territory, untouched here.
- Implementing the chosen mechanism beyond the ADR itself — if build-at-
  deploy is chosen, the actual `post-receive`/`.deployignore` changes are
  a separate follow-up issue (this issue's own acceptance criteria already
  scope implementation out); if the Actions-fallback path is chosen, that
  workflow's implementation is likewise a follow-up unless small enough to
  fold in here (decide once the outcome is known).

## Implementation Checklist

- [x] **User runs the three verification commands above against
      `test.elanregistry.org` and reports results** — confirmed: Node/npm
      resolve non-interactively with no nvm-sourcing workaround needed;
      `npm ci` ~16s (network-bound, 195 packages), `npm run build` <1s,
      measured via a fresh clone into a scratch directory (the live
      deployed tree already had `package.json`/lockfile removed by
      `.deployignore`, confirming that behavior directly). 17 `EBADENGINE`
      warnings noted, all in unrelated devDependencies (test/lint
      tooling), non-fatal.
- [x] Based on reported results, write the decision as either a new
      `docs/development/adr/ADR-018-*.md` (superseding ADR-017) or an
      update to ADR-017 itself reaffirming it with the new evidence —
      `docs/development/adr/` — **ADR-018 written, superseding ADR-017**;
      ADR-017's own status line updated to point forward; ADR index
      (`docs/development/adr/README.md`), `CLAUDE.md`'s ADR-update
      guidance, and ADR-007's vendoring cross-reference all updated to
      cite ADR-018
- [x] If superseding with ADR-018: file a follow-up issue (not implemented
      here) for the actual `post-receive`/`.deployignore`/CI-removal
      implementation work, scoped per the ADR — filed as #1806
- [x] Update `docs/releases/RELEASE_NOTES_v2.29.4.md`'s #1771 entry to
      describe the actual decision made (currently `WIP:`) — `docs/releases/`

## Test Plan

No automated tests — this issue's output is a decision record (ADR) plus,
at most, a follow-up-issue filing. Verification is the SSH check itself
(already specified above) plus confirming the resulting ADR's stated
verdict is unambiguous and its acceptance-criteria checklist (from the
original issue body) is fully addressed by the new/updated ADR content.

## Documentation Plan

- New or updated ADR under `docs/development/adr/` (the core deliverable).
- `docs/releases/RELEASE_NOTES_v2.29.4.md` — strip `WIP:` prefix, describe
  actual decision.
- If ADR-018 (supersede) is chosen: `docs/development/DEPLOYMENT.md` and
  the Wiki's `Registry-Installation-Production.md` need the new Node/nvm
  host-requirement documented — flagged here but actual doc edits deferred
  to the follow-up implementation issue per the Out of Scope section,
  since the mechanism itself isn't built in this issue.
