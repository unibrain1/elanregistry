# Admin Script Creation Guidelines

This document provides comprehensive guidelines for creating database
maintenance and one-time fix scripts for the Lotus Elan Registry.

> **Schema migrations now use Phinx.** One-time schema changes (DDL, FK
> constraints, column changes) belong in `database/migrations/` — not as FIX
> scripts. See [`database/migrations/README.md`](../../database/migrations/README.md)
> for how to create a Phinx migration. FIX scripts remain appropriate for admin
> utility tasks that require human judgment to run.

## Overview

Admin scripts are standardized PHP utilities used for database maintenance and
data correction. They follow a consistent pattern for UI, error handling, and
logging. As of the v2.20.0 restructuring, admin scripts live under
`app/admin/scripts/` and are split into two categories by purpose:

- **`app/admin/scripts/fix/`** — One-time migration / fix scripts. Run once,
  recorded in `fix_script_runs`, then deleted when no longer needed.
  Sequentially numbered (`##-Descriptive-Name.php`).
- **`app/admin/scripts/maintenance/`** — Repeatable system maintenance scripts
  that are safe to run multiple times (e.g., permission audits, thumbnail
  regeneration, orphan cleanup). Sequentially numbered for consistent ordering
  in the admin UI.

If the script runs once and is then done forever, it belongs in `fix/`. If the
script can usefully be re-run as part of routine maintenance, it belongs in
`maintenance/`.

## When creating admin scripts, ALWAYS use the standardized template

1. **Use Template**: Start with
   `app/admin/scripts/fix/_TEMPLATE_Fix-Script.php`
2. **Sequential Naming**: Use format `##-Descriptive-Name.php` (e.g.,
   `13-Fix-Something.php`)
3. **UI Standards**: Maintain two-step process (description → start button →
   progress tracking)
4. **Progress Tracking**: Use `outputMessage()` / `logProgress()` for progress
   updates and step indicators
5. **Logging**: Use simple `INSERT INTO fix_script_runs (script_name) VALUES
   (?)` format
6. **Database**: Always use proper transactions and error handling

## Template Features

The standardized template provides:

- Professional UI with progress bars and status updates
- Standardized completion summaries with statistics
- Proper error handling and rollback capabilities
- Consistent return navigation and logging

## Example Structure

**Copy `app/admin/scripts/fix/_TEMPLATE_Fix-Script.php`** rather than writing a
script from scratch. It is the source of truth for the current pattern; an
example reproduced here would drift from it, as an earlier version of this
section did.

The template wires up the shared infrastructure in
`app/admin/includes/fix-script-core.php`:

| Helper | Role |
| --- | --- |
| `admin_script_exec_requested()` | The gate. Returns true only for a POST with a valid CSRF token from a user passing `isAdmin()`. **Every destructive path must sit behind it** — it is what stops an editor, or a GET request, from executing a script. |
| `admin_script_start_form()` | Renders the confirm-and-execute form that produces that POST. |
| `logProgress($msg, $type)` | Writes to the two-phase progress UI and the run log. |
| `admin_script_close_button()` | Standard return navigation. |

Runs are recorded in `fix_script_runs`, which the admin health and maintenance
tabs read.

> **Schema changes do not belong here.** DDL goes in a Phinx migration, which
> runs at deploy time from the CLI rather than through a web-accessible page.
> See [ADR-009](adr/ADR-009-use-phinx-for-database-schema-migrations.md).

## Key Requirements

1. **Always use transactions** for database operations
2. **Always log errors** using the logger() function
3. **Always validate CSRF tokens** for form submissions
4. **Always provide clear progress updates** to users
5. **Always include return navigation** to the admin maintenance page
6. **Always log script execution** to fix_script_runs table

## Best Practices

- Test on development/staging environment first
- Include detailed progress messages
- Provide meaningful error messages
- Log all significant operations
- Include statistics in completion message
- Document what the script does in comments
- Use descriptive variable names
- Follow established coding standards

## Removing Completed Fix Scripts

When a `fix/` script has been successfully run on production and will never
need to run again, delete it. Git history is the permanent record — the script,
its comments, and the commit that removed it are all recoverable.

Maintenance scripts under `maintenance/` are never removed this way — they are
intended to be re-run, so they stay in place indefinitely.

Scripts were formerly moved to an `app/admin/scripts/fix/_ARCHIVE/` directory
first. That directory was deleted in v2.29.1: keeping executed scripts on disk
made them a copy-as-template trap (several had rotted to the point of fataling
if run) and polluted repo-wide searches, while adding nothing git history did
not already provide.

### When to remove

- The script has run successfully on production
- The underlying data issue is fully resolved
- There is no scenario where it would need to run again

### Removal process

1. Delete the script: `git rm app/admin/scripts/fix/##-Name.php`
2. Commit with message: `chore: remove completed fix script ##-Name`

Removing several at once as part of a milestone is normal — see
`.claude/commands/start-milestone.md`, which prompts for this cleanup when a
new milestone branch is created.

### Recovery

To restore a deleted script from git history:

```bash
git log --all --oneline -- app/admin/scripts/fix/<filename>.php
git show <commit>^:app/admin/scripts/fix/<filename>.php > recovered-script.php
```

Scripts deleted before v2.29.1 lived under `app/admin/scripts/fix/_ARCHIVE/`
(and, before the v2.20.0 restructuring, under `FIX/_ARCHIVE/`) — use that path
in the `git log` command when recovering one of those.

## See Also

- `/app/admin/scripts/fix/_TEMPLATE_Fix-Script.php` - The standardized template
- `/app/admin/scripts/fix/README.md` - Fix scripts directory documentation
- [CODING_STANDARDS.md](CODING_STANDARDS.md) - Coding standards and conventions
