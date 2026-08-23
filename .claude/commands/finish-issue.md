---
description: Monitor CI, squash-merge an issue PR into the milestone branch, and close the issue
model: claude-sonnet-5
---

# Finish Issue

Keep output brief — terse status lines, no preamble, no restating of steps.

## Step 0: Initialize TaskList

Before any other action, create one tracking task per major step below using
TaskCreate. Set to `in_progress` when starting each, `completed` on success.
This is a CI-polling workflow that can take 10+ minutes — visible progress
matters.

Monitor a PR's CI checks, then squash-merge into the milestone branch, close
the issue, delete the branch, and return to the milestone branch.

## Arguments

- `$ARGUMENTS` — the GitHub issue number (e.g., `423`). If omitted, infer
  from the current branch name (e.g., `issue/423-car-data-export` → `423`,
  `bug/512-negative-price` → `512`, `feature/423-export` → `423`).

## Workflow

### Step 1: Determine the issue number and PR

If no argument is provided, extract the issue number from the current branch
name:

```bash
git branch --show-current
```

The branch must match `issue/<number>-*`, `bug/<number>-*`, or
`feature/<number>-*`. If it doesn't, stop and ask the user for the issue
number.

Find the open PR for this issue branch:

```bash
gh pr list --head "$(git branch --show-current)" --state open \
  --json number,title,url,baseRefName,statusCheckRollup
```

If no PR exists, stop and tell the user to run `/commit-push-pr` first.

### Step 2: Identify the target (base) branch

The PR's `baseRefName` should be a `milestone/*` branch. Record it — this is
where we'll return after merging.

If the PR targets `main` instead of a milestone branch, **warn the user** —
issue PRs should always target the milestone branch per the git workflow. Ask
if they want to proceed or retarget the PR.

### Step 2.5: Handle draft PRs — trigger review, then mark ready

Check if the PR is a draft:

```bash
gh pr view <pr-number> --json isDraft --repo elan-registry/registry -q .isDraft
```

**If the PR is a draft:**

1. Trigger the Claude Code Review workflow on the draft PR before notifying
   anyone:

   ```bash
   gh workflow run claude-code-review.yml \
     --ref main \
     --field pr_number=<pr-number> \
     --repo elan-registry/registry
   ```

2. Wait for the workflow run to complete. Poll every 30 seconds:

   ```bash
   # Get the most recent run of claude-code-review.yml
   gh run list --workflow=claude-code-review.yml --limit=1 \
     --repo elan-registry/registry --json databaseId,status,conclusion
   gh run watch <run-id> --repo elan-registry/registry
   ```

3. **The run completing is not proof a review was posted** — a job can
   report success while the action silently skipped (e.g. the workflow-file-
   match guard) or exhausted its turns before calling `gh pr comment`. Verify
   the actual comment exists before trusting the result:

   ```bash
   gh api "repos/elan-registry/registry/issues/<pr-number>/comments" \
     --jq '[.[] | select(.body | test("#{1,6}\\s+Strengths|\\*\\*Strengths\\*\\*"))] | length'
   ```

   (Same "Strengths"-heading pattern the workflow's own gate step uses to
   confirm a real review landed — see #1724.) If the run completed but no
   matching comment exists, treat this the same as a failed trigger: report
   it to the user and do not proceed to marking the PR ready.

4. Report the review result. If the review posted **Blocking** findings, stop
   here and tell the user to fix them before proceeding.

5. Once the review is clean (no Blocking items) and item 3 above confirmed a
   comment was actually posted, mark the PR as ready. This is the moment
   watchers are notified — immediately followed by merge:

   ```bash
   gh pr ready <pr-number> --repo elan-registry/registry
   ```

**If the PR is already ready (not a draft):** proceed to Step 2.6 to verify
the review that should have run on push, before moving to Step 3.

### Step 2.6: Verify the pushed-PR review actually posted (non-draft path)

Skip this step if Step 2.5 already ran (draft PR path) — it already verified
comment presence before marking the PR ready.

For a PR that was already ready-for-review when pushed,
`pr-to-milestone-review` is expected to have run automatically on the
`opened` event. **Do not assume this happened — the same event can be
silently suppressed by GitHub's abuse/rate throttle (see #1724), and even a
"successful" job run does not guarantee a comment was posted** (e.g. the
action's workflow-file-match guard can skip execution without failing the
job — see `claude-code-review.yml`'s header comment block).

Check for the actual review comment, not job status:

```bash
gh api "repos/elan-registry/registry/issues/<pr-number>/comments" \
  --jq '[.[] | select(.body | test("#{1,6}\\s+Strengths|\\*\\*Strengths\\*\\*"))] | length'
```

Poll every ~15s for up to ~2 minutes (`pr-to-milestone-review` is the
lightweight Sonnet job, faster than the milestone-level Fable review).

**If a matching comment is found:** proceed to Step 3 as normal. This step
doesn't inspect the comment for Blocking findings the way Step 2.5 does —
`claude-code-review.yml`'s own gate step already hard-fails the "Claude Code
Review" CI check when Blocking findings are posted, and Step 3/4 below stop
on any failed check. That downstream check is where Blocking findings on a
non-draft PR actually get caught.

**If no matching comment appears after the poll window:**

1. Check whether a run was even triggered:

   ```bash
   HEAD_SHA=$(gh pr view <pr-number> --json headRefOid -q .headRefOid --repo elan-registry/registry)
   gh run list --workflow=claude-code-review.yml --repo elan-registry/registry \
     --json databaseId,headSha,status,conclusion,event \
     --jq --arg sha "$HEAD_SHA" '[.[] | select(.headSha == $sha)]'
   ```

2. Re-trigger via the same mechanism Step 2.5 uses for drafts:

   ```bash
   gh workflow run claude-code-review.yml \
     --ref main \
     --field pr_number=<pr-number> \
     --repo elan-registry/registry
   ```

3. Wait for the new run and re-check for the comment the same way. If it
   still doesn't appear and the PR's diff touches
   `.github/workflows/claude-code-review.yml`, this is the self-referential
   workflow-file skip case — report it distinctly; re-triggering will not
   fix it until the workflow file change is merged to `main`.

4. Report to the user that recovery was needed and what happened — never
   silently proceed to Step 3 without a confirmed comment or an explicit,
   reported reason recovery isn't applicable.

### Step 3: Monitor CI checks

Poll the PR's check status until all checks complete (pass or fail):

```bash
gh pr checks <pr-number> --watch --fail-fast
```

If `--watch` is not available, poll manually:

```bash
gh pr checks <pr-number>
```

Wait 30 seconds between polls. Maximum 20 attempts (10 minutes). If checks
are still pending after 10 minutes, report status and ask the user whether to
keep waiting.

**Expected CI checks** (see DEPLOYMENT.md for details):

- CodeQL Analysis — security scanning
- GitGuardian Security — secret detection
- Claude Code Review — coding standards
- PHPUnit Unit + Regression — behavioral test suite (`tests.yml`, added in #1437; not yet a
  GitHub-required check, so `gh pr checks` is what actually surfaces its status here — this
  step's polling already does that regardless of this list)

### Step 4: Handle check results

**If all checks pass** → run the PHPStan baseline hygiene check (Step 4.5)
first, then report results to the user and **ask for explicit confirmation
before merging**: "All CI checks passed. Ready to squash-merge PR #NNN into
`MILESTONE_BRANCH` and close issue #NNN. Shall I proceed?"
Do NOT merge until the user confirms.

**If any check fails:**

- List which checks failed
- For each failed check, fetch the logs:

  ```bash
  gh run view <run-id> --log-failed
  ```

- Analyze the failure logs and report:
  - Which check failed and why
  - The relevant error messages
  - A suggested fix or next step
- **Stop here.** Do not merge. Tell the user to fix the issue, push the fix,
  and re-run `/finish-issue` when ready.

### Step 4.5: Verify PHPStan baseline hygiene

Per CLAUDE.md's fix-when-you-touch-it policy (see CODING_STANDARDS.md —
PHPStan Baseline Hygiene), any project-owned PHP file this PR modified must
not carry `phpstan-baseline.neon` entries — reported errors on touched files
must be fixed, not grandfathered into the baseline. Check the PR's changed
files against the baseline:

```bash
CHANGED_FILES=$(gh pr view <pr-number> --repo elan-registry/registry \
  --json files --jq '.files[].path')

for f in $CHANGED_FILES; do
  case "$f" in
    *.php)
      if grep -qF "path: $f" phpstan-baseline.neon 2>/dev/null; then
        echo "BASELINE OVERRIDE: $f"
      fi
      ;;
  esac
done
```

**If any modified PHP file appears in `phpstan-baseline.neon`:** stop before
merging. Report the affected file(s) to the user and explain that either:

- the underlying PHPStan errors need to be fixed and
  `composer phpstan:baseline` re-run to drop the now-resolved entries, or
- the user explicitly confirms the pre-existing entry is still valid to carry
  over untouched (e.g. the error is in code outside the lines this PR
  changed).

Do not merge until this is resolved or the user explicitly confirms it's
acceptable to proceed.

**If no modified file appears in the baseline:** proceed to Step 4.6.

### Step 4.6: Documentation drift check

Run before merging, once CI is green.

```bash
composer check:docs
```

That catches structural rot — dead links, stale indexes, ADR drift, dropped
tables, removed symbols. It does **not** catch a doc that describes behaviour
the code never had, so also check what this diff could have falsified:

```bash
gh pr diff <pr-number> --name-only
```

| If the diff touched | Check |
| --- | --- |
| `usersc/classes/**` | `docs/development/CLASSES.md` — do the documented classes, paths and signatures still match? |
| `database/migrations/**` | `docs/development/DATABASE.md` — tables, columns, triggers |
| `composer.json` / `package.json` scripts | `CLAUDE.md` Quick Start Commands, `docs/development/QUICK_REFERENCE.md` |
| `app/api/**` | Endpoint references in `ERROR_HANDLING.md`, `DATATABLES.md`, `SYSTEM_OVERVIEW.md` |
| `app/admin/**` or permission guards | `SYSTEM_OVERVIEW.md` §3, `Page-Security-and-Access-Control` on the wiki |
| Anything user-visible | `docs/guides/`, `docs/reference/` — these are read by car owners |
| A capability added, removed, or newly gated | `SYSTEM_OVERVIEW.md` §6 (deliberately not built) and §7 (built but broken) |

**The trigger is the diff, not a judgment call about significance.** Every
serious documentation defect found in the August 2026 audit was a doc
contradicting code that a merged PR had just changed — a dropped table, a
deleted function, a removed endpoint. Each was mechanically detectable from the
diff; none was caught, because nothing looked.

If a doc needs updating, update it in this PR rather than filing a follow-up.
A doc fix that lands separately from the change it describes is a doc fix that
usually does not land.

**Wiki pages are a separate repository** and cannot be updated from this branch.
If the diff invalidates a wiki page, note it in the merge report so it can be
published with `/publish-wiki`.

### Step 5: Squash-merge the PR

```bash
gh pr merge <pr-number> --squash --delete-branch
```

This squash-merges into the milestone branch and deletes the issue branch
(both local and remote).

### Step 6: Close the GitHub issue

```bash
gh issue close $ARGUMENTS --comment "Resolved via PR #<pr-number>."
```

Remove the "in progress" label if present:

```bash
gh issue edit $ARGUMENTS --remove-label "in progress"
```

### Step 7: Update draft release notes

Read the draft release notes at
`docs/releases/RELEASE_NOTES_v<version>.md` (where `<version>` is extracted
from the milestone branch name, e.g., `milestone/v2.17.0` → `v2.17.0`).

In the "Issues Resolved" section, mark this issue as resolved (remove any
"WIP:" prefix if present).

If the release notes were updated, commit the change:

```bash
git add docs/releases/
git commit -m "docs: mark issue #$ARGUMENTS as resolved in release notes"
```

### Step 7.5: Mark the issue complete in the sprint plan

Look for a sprint plan matching this milestone in the sibling `Plans/` repo
(see `Web/ElanRegistry/CLAUDE.md`):

```bash
ls ../Plans/sprints/<version>.md
```

(where `<version>` is the same one used in Step 7, e.g. `v2.29.3`.)

**If no matching file exists:** skip this step silently.

**If found:** read its sequence line (e.g. `**#1591 → #1547 → #1438 → #1439**`) and check whether issue `#$ARGUMENTS` appears in it.

- **If present and not already marked complete:** prefix the issue's number
  with a checkmark, e.g. `#1591` → `✅#1591`. Preserve the rest of the line
  (arrows, other issue numbers, formatting) exactly. Write the change to
  `../Plans/sprints/<version>.md`.
- **If present and already marked complete:** skip, nothing to do.
- **If issue `#$ARGUMENTS` does not appear in the sequence line at all**
  (e.g. an unplanned bugfix not part of the tracked sprint): make no edit to
  the file. Note in the Step 9 summary that this issue wasn't part of the
  tracked sequence.

This edit is made directly in the `Plans/` repo's working tree — a separate
git repository from this one. Do not commit it; leave it for the user to
review and commit there per that repo's own workflow (same convention as the
`/start-milestone` sprint-plan update).

### Step 8: Return to the milestone branch

```bash
git checkout <milestone-branch>
git pull origin <milestone-branch>
```

Clean up the local issue branch if it still exists:

```bash
git branch -d <issue-branch> 2>/dev/null
```

### Step 9: Report results

Output a summary:

- Issue #`<number>` — closed
- PR #`<pr-number>` — squash-merged into `<milestone-branch>`
- CI review status (from Step 2.5 or 2.6): "posted normally" / "no run was
  triggered — re-triggered, now posted" / "ran but posted nothing —
  self-referential workflow-file change" / etc. — never omit this line
- Documentation — `composer check:docs` result, and any doc updated in this PR
  (or "no doc impact"). Note any **wiki** page needing a separate
  `/publish-wiki` run.
- Branch `<issue-branch>` — deleted
- Release notes updated at `docs/releases/RELEASE_NOTES_v<version>.md`
- Now on `<milestone-branch>`

List remaining open issues in the milestone. Use the direct API (`gh issue list --milestone` can silently return empty results):

```bash
# Get milestone number from the milestone branch name, then query API directly
MILESTONE_TITLE=$(git branch --show-current | sed 's|.*milestone/||' || echo "<milestone title>")
MILESTONE_NUM=$(gh api "repos/elan-registry/registry/milestones" \
  --jq ".[] | select(.title | startswith(\"${MILESTONE_TITLE}\")) | .number")
gh api "repos/elan-registry/registry/issues?milestone=${MILESTONE_NUM}&state=open&per_page=20" \
  --jq '.[] | {number, title}'
```

Suggest next steps:

- **If a sprint plan was found and used in Step 7.5:** walk its sequence
  line left-to-right and recommend the first issue number not marked with
  ✅. Cross-check it's still in the open-issues list from above (it may have
  been closed/consolidated outside this flow); if not, fall back to the next
  unmarked entry that is. Say: "Run `/start-issue <next-issue>` — next in the
  sprint plan sequence." If every issue in the sequence is now ✅ but other
  open issues remain (untracked by the plan), list them separately.
- **If no sprint plan was found/used, or the finished issue wasn't in its
  sequence:** fall back to today's behavior —
  - If open issues remain: "Run `/start-issue <next-issue>` to begin the next
    issue in this milestone"
  - If no open issues remain: "All issues in this milestone are complete. Run
    `/finish-milestone $ARGUMENTS` to create the milestone PR"

## Important

- **Never trust a completed CI review run without confirming its comment
  posted.** A job `conclusion: success` does not prove `gh pr comment` was
  called — GitHub's abuse/rate throttle can suppress the triggering event
  entirely, and the action's own workflow-file-match guard (or turn
  exhaustion) can complete a job while posting nothing. Steps 2.5 and 2.6
  verify actual comment presence, not job status (see #1724).
- **Never force-merge if checks are failing.** Always investigate and report
  first.
- **Never merge with new PHPStan baseline entries on touched files.** CI's
  `reportUnmatchedIgnoredErrors` only catches baseline entries for errors that
  were already fixed — it doesn't catch a modified file that still has
  baseline suppressions. Step 4.5 checks for this explicitly.
- The squash merge keeps the milestone branch history clean — one commit per
  issue.
- If the PR targets `main` instead of a milestone branch, warn the user.
  Issue PRs should always target the milestone branch.
- If the local branch can't be deleted (e.g., you're still on it), switch to
  the milestone branch first.
- This command closes the issue directly. The `Closes #NNN` keyword in the
  milestone PR body (created by `/finish-milestone`) serves as a backup for
  any issues that weren't closed here.
- `Plans/` is a separate private repo, sibling to this one (see
  `Web/ElanRegistry/CLAUDE.md`). Sprint plan files are deleted once a
  milestone is released — a missing file is normal, not an error.
