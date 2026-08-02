# Scripts Directory

Utility scripts for the Elan Registry project.

## Build

### build.js

Minifies first-party JS and CSS using esbuild. Invoked via `npm run build` — run
after editing any source file under `app/assets/js/`, `app/assets/css/`, or
`app/admin/assets/`.

```bash
npm run build
```

## Version Management

### update-version.sh

Updates the `VERSION` file in a development environment from the current git tags.
Run after creating a new tag locally.

```bash
./scripts/update-version.sh
```

## Git Hooks Management

### setup-git-hooks.sh

Configures Git to use the `.githooks` directory for pre-commit and commit-msg
quality checks. Run once per developer after cloning the repo.

```bash
./scripts/setup-git-hooks.sh
```

**What it does:**

- Configures Git to use `.githooks` instead of `.git/hooks`
- Makes all hook files executable
- Verifies installation and tests required tools (PHP, Composer, npx)
- Checks that vendor/ and node_modules/ are present

**Pre-commit hook steps:**

1. PHP coding standards validation (security, types, PHPDoc)
2. Markdown linting for documentation consistency
3. Regression test validation (issue linking)
4. Fast unit tests when critical files are modified

### check-hooks-status.sh

Verifies that git hooks are properly configured and all dependencies are available.
Useful after cloning on a new machine or troubleshooting hook issues.

```bash
./scripts/check-hooks-status.sh
```

### check-coding-standards.php

PHP coding standards checker used by the pre-commit hook. Can also be run directly
to inspect a specific directory.

```bash
php scripts/check-coding-standards.php app/
```

## Plugin Updates

### check-plugin-updates.sh

Weekly cron script that compares installed UserSpice plugin versions against the
upstream `mudmin/usplugins` repository and opens a GitHub issue if any are
outdated. Run once to set up, then forget.

**Cron setup (run once):**

```bash
crontab -e
# Add:
0 9 * * 1 /path/to/repo/scripts/check-plugin-updates.sh >> /tmp/plugin-update-check.log 2>&1
```

Requires `gh` CLI authenticated (`gh auth status`). Creates at most one open
`plugin-update` issue at a time to avoid duplicates.

## Database

### refresh-local-db.sh

Refreshes the local development database from a production SQL dump and masks all
email addresses for developer safety. Optionally rsyncs car images from production.

```bash
# Full refresh (DB + images)
./scripts/refresh-local-db.sh

# DB only (skip image rsync)
./scripts/refresh-local-db.sh --skip-images

# Images only (skip DB refresh)
./scripts/refresh-local-db.sh --images-only

# Use a specific dump file
./scripts/refresh-local-db.sh ~/Downloads/my-dump.sql
```

Default dump path: `~/Downloads/unibrain_registry.sql`.

## Playwright Authentication

These scripts create a saved Playwright authentication state so that tests can run
as an authenticated user without entering credentials on every run. Run once
(or whenever the session expires).

### playwright-auth-setup.js

Saves an authenticated session for **production** (`elanregistry.org`) to
`tests/playwright/.auth/user.json`. Launches a headed browser so you can solve
any CAPTCHA manually.

```bash
export ELAN_USERNAME="your-username"
export ELAN_PASSWORD="your-password"
node scripts/playwright-auth-setup.js
```

### playwright-auth-setup-test.js

Same as above but targets the **test environment** (`test.elanregistry.org`).
Saves state to `tests/playwright/.auth/user-test.json`.

```bash
export ELAN_USERNAME="your-test-username"
export ELAN_PASSWORD="your-test-password"
node scripts/playwright-auth-setup-test.js
```

### playwright-auth-1password.sh

Convenience wrapper: loads production credentials from 1Password and runs
`playwright-auth-setup.js`.

```bash
./scripts/playwright-auth-1password.sh
```

### playwright-auth-1password-test.sh

Convenience wrapper: loads test-environment credentials from 1Password and runs
`playwright-auth-setup-test.js`.

```bash
./scripts/playwright-auth-1password-test.sh
```

## Server Hooks

### server-hooks/post-receive

Git post-receive hook installed on the production and test servers. Handles
deployment when a branch is pushed: checks out the work tree, writes the VERSION
file, runs Composer, executes pending migrations, self-updates the hook from the
deployed tree, and removes dev-only files listed in `.deployignore`.

This file is managed in the repo and self-updates on every push — do not edit
the hook on the server directly.

## Troubleshooting Git Hooks

### Hooks Not Running

**Symptom:** Commits succeed without quality checks running.

```bash
# Check hook configuration
git config core.hooksPath
# Should output: .githooks

# If not configured:
./scripts/setup-git-hooks.sh
```

### Tests Failing Unexpectedly

**Symptom:** Pre-commit hook reports test failures.

```bash
composer install
npm install
composer test:quick
```

### Coding Standards Violations

**Symptom:** Pre-commit blocked with "PHP coding standards violations".

```bash
# Run directly to see detail
php scripts/check-coding-standards.php app/
```

Common issues: missing `declare(strict_types=1)`, missing return type
declarations, missing PHPDoc on public methods, SQL string concatenation.

### Need to Bypass Hooks Temporarily

```bash
# Emergency only — fix issues before merging
git commit --no-verify -m "message"
```

### Getting Help

```bash
./scripts/check-hooks-status.sh   # full status report
git diff --cached --name-only     # verify staged files
```

## Adding New Scripts

1. Place in `scripts/`
2. Make executable: `chmod +x scripts/your-script.sh`
3. Add a section to this README
4. Include usage examples and a `--help` flag where appropriate
5. Use `set -e` for bash scripts
