# Deployment Guide

This document provides comprehensive deployment procedures for the Lotus Elan
Registry application.

## 🚀 Production Environment

### Hosting Infrastructure

- **Hosting**: A2 Hosting with git deployment hooks
- **Remote**: `prod` remote configured for direct deployment to production server
- **Auto-deployment**: Master branch deploys automatically when pushed to prod remote
- **Version Display**: Uses VERSION file modification time for deployment timestamp

### 🚨 CRITICAL: Production Deployment Commands

**⚠️ IMPORTANT:** When someone says "push to prod", always use the `prod`
remote, NOT `origin`!

**Live Production Server:**

```bash
# Push version tags FIRST — the post-receive hook writes VERSION with
# `git describe HEAD` during the branch push, so the tag must already
# be on the server or the footer shows the previous version
git push prod --tags

# Then push code to PRODUCTION SERVER (live site)
git push prod main
```

**GitHub Repository (backup/development):**

```bash
# Push to GitHub for repository backup
git push origin main && git push origin --tags
```

### Remote Configuration Reference

```bash
origin git@github.com:elan-registry/registry.git    # GitHub repository
test   [test-server-path]                            # Test/staging server
prod   [prod-server-path]                            # LIVE PRODUCTION SERVER
```

The real `test` and `prod` paths are deliberately not published — run
`git remote -v` to see the values configured in your clone.

**🔄 Deployment Rule:**

- `origin` = GitHub (development/backup)
- `test` = Test/staging server for validation
- `prod` = **LIVE WEBSITE** (elanregistry.org)

**Testing Feature Branches:**

```bash
# Deploy feature branch to test server
git push test feature/v2.9.1

# Deploy tag to test server
git push test v2.9.1
```

## 🤖 Automated Pull Request Checks

All pull requests to the `main` branch are automatically validated through a
comprehensive set of checks before merge is allowed. These checks ensure code
quality, security, and project management compliance.

### Quick Reference: PR Check Status

| Check Name                    | Purpose               | Blocks | Runs When          |
| ----------------------------- | --------------------- | ------ | ------------------ |
| **CodeQL Analysis**           | Security scanning     | ✅ Yes | All PRs to main    |
| **GitGuardian Security**      | Secret detection      | ✅ Yes | All commits/PRs    |
| **Claude Code Review**        | Coding standards      | ✅ Yes | PHP/JS/CSS changes |
| **Issue Management**          | Auto-label issues     | ❌ No  | Issue events       |
| **PR Management**             | Link PRs to issues    | ❌ No  | PR events          |
| **PHPUnit Unit + Regression** | Behavioral test suite | ❌ No* | All PRs            |
| **Static Analysis**           | PHPStan, phpcs, lint  | ✅ Yes | PHP/JS/MD changes  |

\* Not yet a GitHub-required status check — failures are caught by `/finish-issue`'s CI-status gate
before merge, not by GitHub blocking the merge button itself (see issue #1437).

### Security & Code Quality Checks

#### 1. **CodeQL Analysis**

- **What it does**: Static analysis for security vulnerabilities and code
  quality issues in JavaScript
- **When it runs**: On every pull request to main branch
- **Scope**: Analyzes JavaScript files for common vulnerabilities (XSS,
  injection attacks, etc.)
- **Pass criteria**: No critical security vulnerabilities detected
- **Failure impact**: Blocks merge until vulnerabilities are resolved

#### 2. **GitGuardian Security Checks**

- **What it does**: Scans for secrets, API keys, passwords, and credentials in code
- **When it runs**: On every commit and pull request
- **Scope**: All files in the repository for hardcoded secrets
- **Pass criteria**: No exposed credentials or API keys found
- **Failure impact**: Blocks merge and sends security alerts
- **Configuration**: External service, no local configuration files

#### 3. **Claude Code Review**

- **What it does**: Automated code review against Elan Registry coding standards
- **When it runs**: On every push to a PR (no path filter). Two modes, gated by
  branch shape in `.github/workflows/claude-code-review.yml`:
  - `pr-to-milestone-review` (light, Sonnet) — any branch → `milestone/*`, and
    any non-milestone branch → `main` (hotfix / ad-hoc PRs); skipped for
    `[skip-review]` / `[WIP]` titles
  - `milestone-review` (deep) — `milestone/*` → `main`, on open /
    ready-for-review / `deep-review` label, non-draft only
  - `workflow_dispatch` with `pr_number` re-runs the light review on demand
- **Scope**: Enforces coding standards from `docs/development/CODING_STANDARDS.md`
- **Key checks**:
  - **PHP 8+ Type Safety**: Complete type declarations, `declare(strict_types=1)`
  - **Security**: CSRF tokens, parameterized queries, input validation
  - **Architecture**: Custom exceptions, proper error handling
  - **Documentation**: PHPDoc blocks for public methods
  - **Performance**: N+1 queries, caching opportunities
- **Pass criteria**: No blocking issues (❌), warnings (⚠️) acceptable
- **Review format**: Specific feedback with code examples and standard references

### Project Management Automation

#### 4. **Issue Management Automation**

- **What it does**: Automatically manages GitHub issues with labels,
  milestones, and status tracking
- **When it runs**: On issue creation, updates, and closure
- **Key functions**:
  - **Auto-labeling**: New issues get `status: needs-planning`
  - **Priority assignment**: Based on keywords (critical, bug, enhancement, etc.)
  - **Status transitions**: Removes conflicting status labels
  - **Milestone tracking**: Updates progress when issues close
- **Labels applied**: `priority: critical/high/medium/low`, `status: needs-planning/in-progress/needs-review`

#### 5. **PR Management Automation**

- **What it does**: Links PRs to issues and manages development workflow
- **When it runs**: On PR creation, updates, and merge
- **Key functions**:
  - **Issue linking**: Detects "fixes #123", "closes #456" patterns
  - **Status updates**: Updates linked issues based on PR state
  - **Auto-closure**: Closes linked issues when PR merges
  - **Draft handling**: Marks issues as "in-progress" for draft PRs
- **Status flow**: `status: in-progress` → `status: needs-review` → issue closed

### Automated Test Execution

#### 6. **PHPUnit Unit + Regression**

- **What it does**: Runs the PHPUnit `Unit` and `Regression` testsuites (`composer test:quick:ci`
  and `composer test:regression:ci`) — mocked, no database or network required
- **When it runs**: On every PR (open/synchronize) and on push to `main`
  (`.github/workflows/tests.yml`)
- **Scope**: `tests/unit/`, excluding any test tagged `#[Group('known-broken')]` (see
  `tests/README.md`'s "CI vs. Local Test Runs" section) — the local `composer test:quick` command
  runs the same suite without that exclusion, so developers always see the full picture locally.
  The `Unit` step also excludes `#[Group('regression')]` (`tests/unit/regression/`), which the
  separate "Run regression tests" step below covers instead — see `tests/README.md`'s "The
  `regression` Group Exclusion" section for why the two steps stay disjoint
- **Pass criteria**: All included tests pass
- **Failure impact**: Not (yet) a GitHub-required status check — merge isn't blocked at the
  platform level. Enforcement instead relies on `/finish-issue`'s CI-status gate, which polls
  actual check status and requires explicit confirmation before merging. See
  [#1437](https://github.com/elan-registry/registry/issues/1437) for the rationale.
- **Configuration**: `.github/workflows/tests.yml`; PHP 8.3 via `shivammathur/setup-php`

### Milestone Release PRs

When merging a milestone branch (e.g., `milestone/v2.14.0`) into `main`, the
release PR body **must** include GitHub closing keywords for all issues resolved
in that milestone. Individual PRs merged into the milestone branch target the
milestone branch — not `main` — so their closing keywords won't trigger
auto-closure. Only the final release PR merged into `main` will auto-close
issues.

**Example release PR body:**

```markdown
## Issues Resolved

Closes #533 - Dropzone validation error display
Closes #534 - Mock DB string return types
Closes #535 - Car validation exception test coverage
```

Use `Closes`, `Fixes`, or `Resolves` followed by `#NNN`. See the
[Release Notes Template](RELEASE_NOTES_TEMPLATE.md) for the full format.

### Special Workflow Behaviors

#### Version Check Behavior

- **Feature branches**: Version check **skipped** (allows development work)
- **Main branch**: Full version validation runs (ensures production quality)
- **Why skipped on PR**: Prevents blocking development, validation happens on merge

#### Check Dependencies

- **Required for merge**: CodeQL, GitGuardian, Claude Review (if applicable)
- **Informational only**: Project management automation (doesn't block merge)
- **Manual override**: Repository administrators can override if needed

### Troubleshooting Common Check Failures

#### CodeQL Failures

- **Cause**: Security vulnerabilities in JavaScript code
- **Resolution**: Fix identified vulnerabilities, rerun analysis
- **Common issues**: XSS vulnerabilities, unsafe DOM manipulation

#### GitGuardian Failures

- **Cause**: Hardcoded secrets, API keys, or credentials detected
- **Resolution**: Remove secrets, use environment variables instead
- **Prevention**: Use `.env` (plaintext, chmod 600) or environment variables for sensitive data

#### Claude Review Failures

- **Cause**: Coding standard violations (missing types, CSRF, documentation)
- **Resolution**: Address specific issues mentioned in review comments
- **Reference**: Follow examples and standards in review feedback

## 🛠️ Local Development Tools

### Pre-Commit and Pre-Push Quality Checks

Pre-commit hooks validate PHP coding standards, markdown formatting, and run
fast unit tests before each commit. Pre-push additionally runs the full
integration suite and blocks the push on failure, on pushes touching `app/`,
`usersc/classes/`, or `tests/integration/` (#1439).

```bash
./scripts/setup-git-hooks.sh    # One-time setup
git commit --no-verify           # Bypass pre-commit (emergency only)
git push --no-verify             # Bypass pre-push, including the integration gate
```

**See `scripts/README.md`** ("Git Hooks Management" / "Troubleshooting Git Hooks") for full hook step-by-step details and troubleshooting.

## 📋 Complete Production Deployment Process

### Step-by-Step Deployment

1. **Create git tag**: `git tag vX.Y.Z`
2. **Commit changes** (if any) before creating tag
3. **Push to remotes** - deployment hooks automatically update VERSION file.
   On deployment remotes, push tags **before** the branch — the hook writes
   VERSION during the branch push and needs the tag already present:
   - GitHub: `git push origin main && git push origin --tags`
   - Test: `git push test --tags && git push test main` (hook updates VERSION)
   - Production: `git push prod --tags && git push prod main` (hook updates VERSION)
4. **Run database migrations** (see below)
5. **Verify deployment** by checking version display matches git tag on
   production site
6. **Complete post-deployment verification** (see checklist below)

### Database Migrations

After every deployment, run pending migrations:

```bash
# 1. Verify no orphaned rows that would block the FK migration
#    (must return 0 before applying for the first time)
# SELECT COUNT(*) FROM car_transfer_requests WHERE existing_car_id NOT IN (SELECT id FROM cars);

composer install --no-dev --optimize-autoloader   # ensure vendor/ is up to date
composer migrate:status                            # preview what will run
composer migrate:dry-run                           # confirm SQL before applying
composer migrate                                   # apply pending migrations
```

**If a migration fails:** The runner stops at the failed migration and exits non-zero. Fix the migration file
and redeploy — Phinx retries only the failed migration (already-applied ones are skipped).

**Check migration status at any time:**

```bash
composer migrate:status   # list pending and applied migrations
```

**Automated deployment:** `git push prod main` runs `composer install` and `composer migrate`
automatically via the post-receive hook. The manual steps above serve as a fallback if the hook needs to
be bootstrapped on a fresh server.

### One-Time: Stamping the ElanRegistry Baseline Migration

`database/migrations/20260709000000_add_elanregistry_baseline.php` reproduces the full
ElanRegistry-vs-stock-UserSpice schema diff as a single migration — the schema-of-record for any
newly provisioned environment (`scripts/provision-schema.sh`). Dev and prod already have this exact
schema natively; their databases predate Phinx and were never migrated through it. Running the
baseline migration for real against either would try to `CREATE TABLE car_models` (and 12 other
already-existing tables) and fail immediately.

**Take a full database backup through the host's phpMyAdmin before you begin** (Export → Custom →
all tables → SQL, structure and data). `git push` triggers the post-receive hook, which runs
`composer migrate` immediately — once you push there is no window to intervene, so the backup and
the stamp below must both be done first. A phpMyAdmin export is the rollback path if a migration
behaves unexpectedly against real data; the application's own backup feature runs against the same
database being changed and is not a substitute.

**Before the next `composer migrate` runs on dev or prod** (once, the first time this migration is
ever deployed there — not a repeatable step), manually mark it as already-applied instead of running
it:

```sql
INSERT INTO phinxlog (version, migration_name, start_time, end_time, breakpoint)
VALUES (20260709000000, 'AddElanregistryBaseline', NOW(), NOW(), 0);
```

Verify first that this hasn't already been stamped (`SELECT * FROM phinxlog WHERE version =
20260709000000`) — the `PRIMARY KEY` on `version` makes a duplicate `INSERT` fail loudly rather than
silently, but check anyway before running it against a production database. After the stamp,
`composer migrate` skips `20260709000000` and applies only the migrations genuinely pending on that
environment, exactly like any other deploy.

### Git & Version Control

#### Branch Management Strategy

- `main` branch always contains production-ready code
- All development work happens on feature/phase branches
- Direct commits to main are discouraged

#### Branch Naming Convention

- Milestone branches: `milestone/v{X.Y.Z}` (created by `/start-milestone`)
- Issue branches: `issue/{number}-brief-description` (created by `/start-issue`)
- Bug fix branches: `bug/{number}-brief-description` (created by `/start-issue`)
- Feature branches: `feature/{number}-brief-description` (created by `/start-issue`)
- Hotfix branches: `hotfix/issue-{number}-brief-description`

#### Version Management & Git Tag-Based Versioning

**Automated VERSION File Generation:**

- VERSION file is **auto-generated during deployment** (not manually edited)
- Git post-receive hooks run `git describe --tags > VERSION` on push
- VERSION file added to `.gitignore` (not tracked in git)
- Each environment generates its own VERSION file from its git repository
- Format: `vX.Y.Z` or `vX.Y.Z-N-gHASH` (semantic versioning via git describe)

**Deployment Hooks:**

Test and production servers have a single shared post-receive hook
(`scripts/server-hooks/post-receive`) that automatically:

1. Checkout latest code
2. Run `git describe --tags` and write VERSION file
3. Run `composer install --no-dev --optimize-autoloader`
4. Run `php vendor/bin/phinx migrate` (halts deployment on failure)
5. Run `npm ci --omit=dev && npm run build` to (re)generate vendored frontend
   assets in `usersc/js/`/`usersc/css/` (halts deployment on failure). These
   directories are gitignored build output, not committed — see
   [ADR-018](adr/ADR-018-build-at-deploy-for-frontend-vendoring.md)
6. Self-update the installed hook from the newly deployed working tree
7. Remove dev-only paths listed in `.deployignore` (e.g. `tests/`, `scripts/`,
   `utilities/`, `wiki/`) via `rm -rf`, then remove `.deployignore` itself

**Host requirements:** steps 3-5 require Composer, PHP, and Node/npm to be
resolvable non-interactively on the deploy host's login shell (no `nvm use`
or similar interactive step). Verified against `test.elanregistry.org` during
ADR-018's research: Node v18.20.8 / npm 10.8.2, no shell workaround needed.

**Important:** Never list a persistent, server-writable directory in
`.deployignore` (e.g. `backups/`) — step 7's `rm -rf` would delete it wholesale
on every subsequent push. See #1479, where this happened to the server's
backup directory.

**Important — deploying a change to `post-receive` itself takes two pushes,
not one:** Step 6's self-update copies the new hook file to disk *during* the
currently-running invocation, but that invocation is already executing the
*old* code — it can't reload itself mid-run. So the first push after any
change to `scripts/server-hooks/post-receive` still executes the **old**
hook logic end-to-end (any new steps added in that change do not run yet);
it only installs the new file for the *next* invocation.

A second push is required to actually exercise the new logic — and it must
be a **genuine** push. `git push` short-circuits client-side when the remote
already has the commit ("Everything up-to-date") and never contacts the
server's hook at all, so simply repeating the same push does nothing. Force
an empty commit instead:

```bash
git push test main                                    # 1st push: old hook runs, self-updates the file
git commit --allow-empty -m "chore: trigger post-receive hook rerun"
git push origin main                                  # keep origin in sync
git push test main                                     # 2nd push: new hook logic runs for the first time
```

Repeat independently for `prod` when you deploy there — each environment's
installed hook is updated (and needs re-triggering) on its own schedule,
based only on when that specific remote last received a push.

**Development:**

Run `./scripts/update-version.sh` to generate VERSION file locally after creating tags.

**Version Display:**

- `ApplicationVersion::get()` reads VERSION file (unchanged)
- Deployment timestamp shows VERSION file modification time
- Example output: `v2.9.1-rc1 (2025-12-14 10:30:00)`

## ✅ Post-Deployment Configuration Requirements

**CRITICAL:** After deploying code changes to production, always verify and update:

### UserSpice Page Permissions

- **Problem:** New pages and admin scripts need proper access permissions registered
- **Solution:** Run `app/admin/scripts/maintenance/21-Fix-Page-Permissions.php`
  on test, verify, then run on prod — this scans all pages and corrects their
  permission entries in UserSpice's `pages` table
- **Required whenever:** A new page, admin script, or route is added or renamed

### Deployment Verification Checklist

After each deployment, verify:

- [ ] Maps display correctly: world map on Statistics page, single-marker map on car Details pages (no API key required — uses self-hosted MapLibre GL JS + VersaTiles)
- [ ] All redirected pages work and maintain proper permissions
- [ ] **Purge Cloudflare cache for any static file the release moved, renamed, or
      deleted** (CSS/JS/PDF/image paths). Static assets are served with
      `cache-control: max-age=31536000`, so the edge keeps returning the old
      200 for up to a year after the origin starts returning 301/404 — the
      v2.29.6 deploy left `/docs/assets/document-content.css` cached this way.
      Cloudflare dashboard → Caching → Purge by URL, one entry per old path.
      Run `npm run test:e2e` afterwards; the redirect specs hit those URLs.
- [ ] New pages have appropriate UserSpice permission levels
- [ ] Contact forms send to correct email addresses
- [ ] VERSION file exists on server (created by deployment hook)
- [ ] Deployment hooks executed successfully (check server logs)
- [ ] Test critical user workflows (car registration, editing, contact forms)
- [ ] Database connectivity and functionality
- [ ] Email delivery system functioning
- [ ] Image upload and display working
- [ ] Search and filtering functionality
- [ ] Mobile responsiveness maintained

### Database Access

See [ENVIRONMENT.md](ENVIRONMENT.md) for database credentials, MySQL binary path, and connection commands.

## 🛠️ Environment Variables

See [ENVIRONMENT.md](ENVIRONMENT.md) for complete environment configuration (database
credentials, API keys, CAPTCHA keys, phpdotenv plaintext `.env` with `chmod 600`).

See [ENVIRONMENT.md](ENVIRONMENT.md) for `.env` setup steps.

### UserSpice Plugins

**Active Plugins:**

- `Auto Assign Usernames` - Hides username field and auto-assigns usernames
  on registration
- `getSettings Function` - Provides global settings access via getSettings()
  function
- `hooker` - Custom hooks system for code injection points
- `Brevo Sendinblue` - API-based email delivery replacing phpmailer
  (300 emails/day free)

## 🚨 Troubleshooting

### Common Deployment Issues

1. **Version mismatch**: Ensure VERSION file content matches git tag exactly
2. **Permission errors**: Check UserSpice admin panel for new page permissions
3. **Map not rendering**: Check browser console for CSP violations; verify
   `usersc/js/maplibre-gl.min.js`, `usersc/css/maplibre-gl.css`, and
   `usersc/js/versatiles-colorful.json` are deployed
4. **Email not working**: Check Brevo/Sendinblue API configuration
5. **Database connection**: Verify production database credentials

### Rollback Procedure

If deployment fails:

1. **Immediate rollback**: `git push prod previous-working-tag`
2. **Verify rollback**: Check version display and core functionality
3. **Investigate issue**: Review error logs and deployment differences
4. **Fix and redeploy**: Address issues and follow deployment process again

### Emergency Contacts

- **Hosting Support**: A2 Hosting technical support
- **Domain Management**: Check domain registrar for DNS issues
- **Database Issues**: Contact hosting provider database support

---

**📖 Related Documentation:**

- [CLAUDE.md](../../CLAUDE.md) - Essential development guidance
- [Development Workflow](https://github.com/elan-registry/registry/wiki/Development-Workflow) - Development processes (wiki)
- [ENVIRONMENT.md](ENVIRONMENT.md) - Environment setup and configuration
