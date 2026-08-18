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

3. Report the review result. If the review posted **Blocking** findings, stop
   here and tell the user to fix them before proceeding.

4. Once the review is clean (no Blocking items), mark the PR as ready. This
   is the moment watchers are notified — immediately followed by merge:

   ```bash
   gh pr ready <pr-number> --repo elan-registry/registry
   ```

**If the PR is already ready (not a draft):** skip to Step 3 — the Claude
review already ran when the PR was pushed.

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

- If open issues remain: "Run `/start-issue <next-issue>` to begin the next
  issue in this milestone"
- If no open issues remain: "All issues in this milestone are complete. Run
  `/finish-milestone $ARGUMENTS` to create the milestone PR"

## Important

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
