---
description: Render the current milestone's derived state — theme, issue status, blocked items
model: claude-haiku-4-5
---

# Sprint Status

Keep output brief — terse status lines, no preamble.

Renders the readable view of the open milestone from `docs/development/ISSUE_WORKFLOW.md`'s
"Tracking" section: milestone + derived state + the one manual label, no
board to keep in sync. Read-only — this command makes no changes.

## Arguments

- `$ARGUMENTS` — optional milestone version (e.g., `v2.17.0`). If omitted,
  find the currently open milestone automatically.

## Step 1: Identify the milestone

```bash
gh api repos/elan-registry/registry/milestones --jq '.[] | select(.state == "open") | {number, title, description}'
```

If `$ARGUMENTS` was given, match against it. If omitted and exactly one
milestone is open, use it. If more than one is open, list them and ask the
user which to report on.

## Step 2: Pull all issues in the milestone, all states

```bash
gh api "repos/elan-registry/registry/issues?milestone=<NUMBER>&state=all&per_page=100" \
  --jq '.[] | {number, title, state, labels: [.labels[].name]}'
```

## Step 3: Derive state for each issue

For each open issue, determine derived state in this priority order:

1. **Blocked** — has `status:blocked` label (the only hand-set state).
2. **In review** — has an open PR. Check:

   ```bash
   gh pr list --repo elan-registry/registry --search "linked:NNN is:open" --json number,title,url
   ```

3. **In progress** — a branch exists for the issue but no open PR yet:

   ```bash
   git ls-remote --heads origin "issue/NNN-*"
   ```

4. **Ready** — has `status:ready` label, no branch found.
5. **Unlabelled/other** — flag distinctly; this means it entered the
   milestone without going through `/plan-milestone`'s sealing step.

Closed issues are **Done**.

## Step 4: Render the report

```text
## Sprint Status — <milestone title>

Theme: "<milestone description>"

### Done (N)
- #NNN Title

### In review (N)
- #NNN Title — PR #NN

### In progress (N)
- #NNN Title — branch issue/NNN-slug

### Ready (N)
- #NNN Title

### Blocked (N)
- #NNN Title — <reason, from the status:blocked comment/context if findable>

### Needs attention
- #NNN Title — no status label, entered milestone outside /plan-milestone

Theme sentence true yet? <yes/no/partial — one line of reasoning>
```

For "theme sentence true yet", judge based on what's Done vs. what remains —
per the doc, the milestone ships when the theme is true, not when the issue
list is empty. If most theme issues are done and what's left is
housekeeping or a low-value straggler, say so.

## Important

- This command is read-only. It never edits issues, labels, or milestones.
- `status:blocked` is the only state not derivable from git/GitHub activity
  — if an issue is stuck for a reason with no trace in the repo (e.g.
  waiting on an owner's reply), that's exactly what the label is for. This
  command surfaces it, not diagnoses it.
- Run any time during a milestone — this is a status check, not a workflow
  step with prerequisites.
