---
description: Execute an approved plan file from /start-issue — implementation, tests, and reviews
model: claude-sonnet-5
---

# Execute Plan

Keep output brief — terse status lines, no preamble, no restating of steps.

## Hard Constraints (non-negotiable)

> **1. THE PLAN FILE MUST BE APPROVED before this command implements anything.**
> If the plan file's status line is not `Approved`, stop and tell the user to
> run `/start-issue` to finish planning and approval first.
>
> **2. NEVER commit, push, or create PRs.**
> After implementation is complete, stop. The user commits explicitly via
> `/commit` or `/commit-push-pr`. Do not run `git add`, `git commit`, or
> `git push` under any circumstances during this workflow.

---

This command implements an approved plan file written by `/start-issue`. It
re-verifies the plan's checklist against actual repo state before doing any
work, so it is safe to run on a fresh branch, resume a partially-completed
plan, or be run by a second agent/session picking up after an interruption —
in every case it establishes ground truth from the files themselves, not from
assumptions about what "should" have happened.

## Arguments

- `$ARGUMENTS` — (optional) an issue number or a path to a plan file. If
  omitted, auto-detect from the current branch name (e.g.,
  `issue/423-car-data-export` → `docs/plans/issue-423-car-data-export.md`),
  the same inference `/finish-issue` uses.

## Step 0: Initialize TaskList

Create tasks: locate + validate plan file, re-verify checklist against repo
state, execute remaining items (fanned out per plan annotations), run
test/security/architect review steps from the plan, update checklist +
release notes, final hand-off summary. Set each `in_progress`/`completed` as
you progress.

## Workflow

### Step 1: Locate the Plan File

If `$ARGUMENTS` is a path ending in `.md`, use it directly. If it's an issue
number, look for `docs/plans/issue-<NUMBER>-*.md`. If omitted, extract the
issue number from the current branch (`git branch --show-current`) the same
way `/finish-issue` does, then locate the matching plan file.

If no matching file exists, stop and tell the user: "No plan file found for
this issue. Run `/start-issue <NUMBER>` first."

If multiple files match (e.g. a stale plan from an earlier, differently-named
attempt), list them and ask the user which to use.

### Step 2: Validate Approval Status

Read the plan file's `**Status:**` line.

- **`Approved — ready for /execute-plan`**: proceed to Step 3.
- **`Draft — pending approval`**: stop. Tell the user: "This plan hasn't been
  approved yet. Return to `/start-issue` to finish the approval step before
  running `/execute-plan`."
- **Anything else** (e.g. already marked complete, or an unrecognized value):
  stop and show the user the actual status line, ask how they want to
  proceed — do not guess.

### Step 3: Re-Verify Checklist Against Repo State

This is the step that makes the plan file trustworthy to any agent or session
that picks it up — never assume the checklist's `[ ]`/`[x]` marks reflect
reality; confirm each one against the actual repository.

For each checklist item:

- **File-creation/modification items** ("Add X to file.php"): check whether
  the described change is actually present in the file (grep for the
  function/method/class name, or read the relevant section).
- **Test items** ("Add PHPUnit test for X"): check whether the test file and
  the specific test method exist.
- **Review items** ("Run security-review", "Run senior-architect review"):
  these cannot be verified by file inspection alone — treat as done only if
  the plan file's checklist already shows `[x]` AND a later step in this same
  workflow run didn't just invalidate that review by changing the reviewed
  files. If uncertain, re-run the review rather than trust a stale checkmark.

Produce a status report: which items are genuinely done (matches both the
checkbox and the actual repo state), which are marked done but aren't
actually present (repo state contradicts the checkbox — flag this explicitly,
it means a previous run's edit was reverted, lost, or never actually
committed to disk), and which are genuinely not started.

**If any item is marked `[x]` but repo state contradicts it**, use
AskUserQuestion before proceeding — do not decide this yourself and do not
silently re-do or silently trust the checkbox:

- Question: "Plan says '`<item>`' is done, but I don't see it in `<file>`.
  How should I proceed?"
- Options: `Treat as not done, redo it` (recommended), `It's actually done —
  update the checkbox, don't redo`, `Let me look myself first`

This discrepancy is exactly the kind of drift the re-verification step
exists to catch, so it always goes to the user — never resolved
automatically, since only they can know whether the missing evidence means
lost work or a false-positive check in the earlier run.

Update the plan file's checkboxes to match verified reality before continuing.

### Step 4: Determine Fan-Out from Plan Annotations

Group the remaining (`[ ]`) checklist items by their annotations:

- Items marked `(parallel-safe)` with no unresolved `(depends on: ...)` can
  run concurrently — launch one `software-developer` agent per independent
  item or tightly-coupled group (same grouping logic as the old `/start-issue`
  Step 10: one agent per independent file or group of related files).
- Items marked `(depends on: <other item>)` wait until that item is verified
  complete (Step 3) or completed earlier in this same run.
- If the plan gives no annotation for an item (older plan file, or an
  oversight), treat it conservatively as **not** parallel-safe — run it
  sequentially rather than guess.

### Step 5: Implement

Launch `software-developer` agents per Step 4's grouping. Provide each agent:

- The specific checklist item(s) it owns, verbatim from the plan file
- The plan's Architecture & Design section for context
- Any Database & Security Considerations relevant to its files

**Model override by tier** (infer tier from the plan file's scope — number of
checklist items and files touched, same Small/Medium/Large bands
`/start-issue` used): pass `model: "sonnet"` for Small-tier plans. Omit
`model` for Medium/Large (agent default is Opus).

As each agent completes, mark its checklist item(s) `[x]` in the plan file
immediately — do not batch updates to the end. This is what lets a second
agent or a later session trust the file's state without re-running Step 3
from scratch.

### Step 6: Test, Security, Documentation

Launch in parallel, only the agents relevant to what changed (per the plan's
Test Plan / Documentation Plan sections):

- **senior-test-engineer**: write and run tests from the plan's Test Plan.
  Separate instances for PHPUnit vs Playwright if both apply.
- **technical-documentation-writer**: update docs per the plan's Documentation
  Plan.

Run quality checks: relevant test suites, and note that pre-commit hooks will
run PHPStan/phpcs on staged files at commit time regardless.

Mark the corresponding checklist items `[x]` as each completes.

### Step 7: Security and Architect Review

- **Run `/security-review`** (security-reviewer agent) if the plan's Database
  & Security Considerations section is non-empty, or any changed file
  touches forms/SQL/auth. Address Critical/High findings before proceeding.
- **Launch `senior-architect`** for final review of the complete diff:
  security verification, database verification, code quality, standards
  adherence, test coverage, documentation completeness. Defaults to Opus;
  pass `model: "sonnet"` explicitly for Small/Medium-tier plans if a cheaper
  run is preferred.

Address any findings — loop back to Step 5 (software-developer agents) for
fixes, then re-run the specific review that flagged the issue, not the whole
step.

Mark the corresponding checklist items `[x]` once each review is clean.

### Step 8: Confirm Plan Completeness

Before moving to hand-off, re-scan the plan file: every checklist item should
now be `[x]`. If any remain `[ ]`, use AskUserQuestion rather than deciding
yourself — even when it looks like the item turned out to be unnecessary:

- Question: "'`<item>`' is still unchecked. How should I handle it?"
- Options: `Mark N/A with a reason` (only offer this when you can state why
  it turned out unnecessary), `Do the work now`, `Let me look myself first`

Do not silently leave unchecked items with no explanation, and do not
silently mark something N/A on your own judgment — that defeats the purpose
of a plan a later step can trust.

Update the plan file's status line to `**Status:** Implemented — pending
commit/PR`.

### Step 9: Update Draft Release Notes

Same as the former `/start-issue` Step 10's release-notes update, adapted for
the fact that this command runs on the **issue branch**, which has no
version string in its own name — unlike `/start-issue`, which determines the
milestone branch directly (its own Step 3) before ever branching off it.

Get the milestone version from the plan file's `**Milestone:**` field first:

```bash
grep -oP '(?<=\*\*Milestone:\*\* `)[^`]+' docs/plans/issue-<NUMBER>-<slug>.md
```

**If that field is missing** (an older plan file predating this field, or one
edited by hand), fall back to resolving it live:

```bash
git branch --list 'milestone/*'
```

If exactly one exists, use it. If zero or multiple exist, stop and ask the
user which milestone branch this issue belongs to — do not guess.

Once resolved, update `docs/releases/RELEASE_NOTES_v<version>.md` (create
from `docs/development/RELEASE_NOTES_TEMPLATE.md` if it doesn't exist yet).
Add this issue's changes to the appropriate section, keep it cumulative, use
the `technical-documentation-writer` agent for non-trivial entries.

### Step 10: Hand Off

**Do NOT commit, push, or create PRs.** State plainly that implementation is
complete and the plan file at `docs/plans/issue-<NUMBER>-<slug>.md` shows
every item verified complete. Then use AskUserQuestion instead of a
plain-text menu — and only ever offer the actual next runnable step, not the
full remaining sequence at once:

- Question: "Implementation complete. What next?"
- Options: `/simplify` (recommended — clean up the code before committing),
  `/commit` (skip straight to committing), `Ask more questions / discuss
  first`
- If the user picks a command, invoke it immediately via the Skill tool
  rather than telling them to type it.

The full remaining sequence, each step handed off the same way once the
prior one completes — do not present this whole list to the user at once,
re-offer one step at a time as each becomes the actual next action:

1. `/simplify` (optional)
2. `/commit`
3. `/review-pr` — **must run after `/commit`, not before.** It diffs
   committed history (`merge-base..HEAD`) against the milestone branch, per
   its own Step 2 — running it on uncommitted changes reviews nothing
   real. After `/commit` completes, offer `/review-pr` as the next step,
   not `/commit-push-pr` directly.
4. `/commit-push-pr` (only once `/review-pr` reports clean, or the user
   explicitly accepts its recommendations as-is)
5. `/address-pr-comments` (after CI runs on the pushed PR)
6. `/finish-issue` (once `/address-pr-comments` reports clean)

**For bug-fix plans** (plan file has a Bug Escape Analysis section), remind
the user to include the escape analysis in the PR description.

**Delete the plan file** once the user confirms the PR is merged and the
issue is closed (typically during/after `/finish-issue`) — its job (a
verifiable record other agents/sessions could check against) is done once
the code is merged; the merged diff and closed issue are then the source of
truth, same lifecycle as milestone sprint plans. Do not delete it from within
this command — that happens later, at merge time, not here.

## Available Agents

| Agent | `subagent_type` | Model | Use When |
| --- | --- | --- | --- |
| Software Developer | `software-developer` | `sonnet` (Small), `opus` (Medium/Large) | **Primary coding agent** |
| Senior Architect | `senior-architect` | `opus` | Post-implementation review |
| Senior Test Engineer | `senior-test-engineer` | `sonnet` | Writing/running tests from the plan |
| Technical Documentation Writer | `technical-documentation-writer` | `haiku` | Docs updates from the plan |
| Security Reviewer | `security-reviewer` | (per agent default) | `/security-review` |

## Critical Rules

- **NEVER implement against an unapproved plan** — Step 2 is a hard gate.
- **NEVER commit, push, or create PRs** — hand off at Step 10, always.
- **Re-verify before trusting the checklist** — Step 3 runs every time this
  command starts, even on a plan that looks fully checked-off already. A
  stale or falsely-checked item is worse than a slow verification pass.
- **Update the plan file incrementally, not in one batch at the end** — mark
  each item `[x]` as its agent completes, so the file is always an accurate
  snapshot if this command is interrupted or resumed by another session.
- **Respect the plan's parallel-safety annotations** — don't parallelize an
  item the plan marked as dependent, and don't invent parallelism for
  unannotated items.
- **Surface discrepancies, never silently resolve them** — a checked item
  that isn't actually in the repo, or unchecked items with no remaining work,
  both get raised to the user via AskUserQuestion (Steps 3 and 8), never
  decided unilaterally.
- **Never assume — verify via code or ask.** Objective facts about the repo
  (does the file exist, does the test pass, is this function already
  implemented) get checked directly — grep/read/run it, don't guess and
  don't ask the user something you can verify yourself. Judgment calls
  (resolve this discrepancy which way, is this item really unnecessary) go
  to AskUserQuestion. Never present a checklist item as done, or a plan as
  complete, without having done one of the two.
- **Use AskUserQuestion for every discrepancy, completeness gap, and
  hand-off choice** (Steps 3, 8, 10) — not free-form chat questions.
- **Follow project conventions** from CLAUDE.md and CODING_STANDARDS.md.
