<?php

declare(strict_types=1);

use ElanRegistry\Admin\PagePermissionClassifier;
use Phinx\Seed\AbstractSeed;

/**
 * Register every `securePage()`-protected page into `pages` and
 * `permission_page_matches` (#1671).
 *
 * UserSpice populates `pages` lazily: a row is only written the first time an
 * Administrator visits that page and `securePage()` runs `createPages()`. A
 * scripted install (`scripts/provision-schema.sh`) never visits any page as
 * an admin, so on a fresh database both tables stay empty and the
 * application is effectively unusable until someone manually clicks through
 * every protected page while logged in as an admin.
 * `app/admin/scripts/maintenance/21-Fix-Page-Permissions.php` looks like the
 * fix but is not: it only reconciles permissions for pages already present
 * in `pages` — on an empty table it is a no-op.
 *
 * This seed walks the real page inventory and inserts the missing rows
 * directly, using `ElanRegistry\Admin\PagePermissionClassifier` (the same
 * classifier `21-Fix-Page-Permissions.php` uses) so the two never disagree
 * about how a page should be classified. It does not duplicate that
 * classifier's logic. For a page that already has a `pages` row, this seed
 * only ever adds `permission_page_matches` rows to make its match count meet
 * what classification expects — healing drift from a cause outside this
 * seed's own control, since `registerNewPage()` wraps both the `pages` and
 * `permission_page_matches` inserts in one transaction and can't itself
 * leave a page partially registered. The drift this heals comes from
 * elsewhere: a `pages` row created by UserSpice's lazy `createPages()`
 * before this seed ever ran, a pre-#1671 version of this seed that predates
 * the transaction wrap, or manual DB intervention. It never removes or changes
 * a match, and it never revises a page whose match count already meets expectations, even if
 * classification would now produce a different set — reconciling that kind
 * of drift (e.g. a page's classification changing after it was first
 * registered) remains `21-Fix-Page-Permissions.php`'s job.
 *
 * A small fixed list (`ROOT_PAGES`) covers the two lone repo-root pages that
 * sit outside every scanned directory. Everything else uses two strategies
 * depending on directory. For `app/`,
 * `usersc/`, and `docs/`, whether a given `.php` file is a "page" is
 * determined by grepping its own contents for a genuine `securePage()` call
 * (`if (!securePage(...))`, the only call shape used anywhere in this
 * codebase). That single check is what naturally excludes files that must
 * never be registered — `app/action/`, `app/api/cars/`, `app/api/shared/`
 * (CLAUDE.md requires these skip `securePage()` entirely) — and what
 * naturally includes `app/api/contact/*.php`, without hardcoding either
 * list. A handful of files still need explicit exclusion because they match
 * the call pattern for reasons unrelated to being a real, independently
 * routable page:
 *
 * - `users/helpers/permissions.php` defines `securePage()`; it doesn't call it.
 * - `usersc/plugins/ai_prompts/**` are AI-context documentation files
 *   (`*.md.php`) that contain the call pattern only inside example code blocks.
 * - `usersc/includes/example_*.php` is UserSpice's unrenamed example
 *   scaffolding — never `require`d by anything until a site owner copies and
 *   renames it.
 * - `users/_blank_pages/**` is UserSpice's stock unused page template — never
 *   `require`d by anything, referenced only from documentation.
 * - `users/modules/**` and `users/views/**` are includes/partials consumed by
 *   another page (e.g. `users/admin.php`), not independently routable pages
 *   in their own right.
 * - `_TEMPLATE_Fix-Script.php` is scaffolding, not a runnable script.
 *   `21-Fix-Page-Permissions.php` already skips `_TEMPLATE_` paths when
 *   reconciling; this seed skips them too so it never registers a
 *   non-runnable script as a live page.
 *
 * `usersc/login.php` and `usersc/join.php` are public-by-design mirrors of
 * `users/login.php` / `users/join.php` (see
 * `PagePermissionClassifier::PUBLIC_USERSC_PAGES`) and, being public entry
 * points, never call `securePage()` themselves — the grep strategy above
 * would otherwise never discover them at all. Left undiscovered, they were
 * never inserted by this seed; UserSpice's own lazy `createPages()`
 * (triggered by an admin visiting some other unregistered page during the
 * same session, not by a direct visit to these two) inserted them first
 * using `settings.page_default_private` — which defaults to private — and
 * the idempotency guard in `run()` then permanently skipped correcting them.
 * `ROOT_PAGES` uses the identical pattern for the same reason (`z_us_root.php`
 * never calls `securePage()` either); these two belong in the same
 * explicit-list bucket.
 *
 * `users/` is walked unconditionally instead — every `.php` file found there
 * (minus the same includes/partials exclusions) is registered regardless of
 * whether it calls `securePage()` directly. Most core UserSpice pages
 * (`login.php`, `join.php`, `logout.php`, `index.php`, the passkey/TOTP/
 * verification flows, etc.) never call `securePage()` themselves — they're
 * public by UserSpice installer convention, or gate access some other way —
 * so grepping for the call would silently skip them.
 * `PagePermissionClassifier::getUserSpiceInstallerSpec()` already encodes
 * the correct default for every `users/*.php` file, including the
 * public-by-default fallback for anything not in its explicit map, so
 * classification doesn't depend on the grep either.
 *
 * Idempotent: every insert is individually guarded by a `SELECT` first,
 * mirroring the duplicate-guard pattern `21-Fix-Page-Permissions.php` uses
 * when applying its own fixes. Neither `pages` nor `permission_page_matches`
 * has a unique constraint to lean on instead.
 */
final class PageRegistrationSeed extends AbstractSeed
{
    /** UserSpice built-in permission id for the "User" (owner) tier. */
    private const PERM_USER = 1;

    /** UserSpice built-in permission id for the "Administrator" tier. */
    private const PERM_ADMIN = 2;

    /** UserSpice built-in permission id for the "Editor" tier. */
    private const PERM_EDITOR = 3;

    /** @var list<string> Repo-root-relative directories grep-scanned for a genuine `securePage()` call. */
    private const SECURE_PAGE_SCAN_DIRECTORIES = ['app', 'usersc', 'docs'];

    /** Repo-root-relative directory walked unconditionally, classified via `getUserSpiceInstallerSpec()`. */
    private const USERSPICE_INSTALLER_DIRECTORY = 'users';

    /**
     * @var list<string> Lone repo-root `.php` files that are real pages but sit outside every
     *                    scanned directory. `index.php` genuinely calls `securePage()` (would be
     *                    found by the grep strategy if root were scanned recursively, which it
     *                    isn't, to avoid pulling in every other root-level script). `z_us_root.php`
     *                    is the front-controller/bootstrap file — it never calls `securePage()`
     *                    itself, but dev and prod both carry a `core=1`, public `pages` row for it,
     *                    and CLAUDE.md documents it as the holder of the `$path` routing array, so
     *                    it is registered unconditionally to match existing installs.
     */
    private const ROOT_PAGES = ['index.php', 'z_us_root.php'];

    /**
     * @var list<string> Public-by-design pages that never call `securePage()` themselves
     *                    (so the grep strategy can't discover them) and therefore need
     *                    explicit registration, same rationale as `ROOT_PAGES`. Mirrors
     *                    `PagePermissionClassifier::PUBLIC_USERSC_PAGES`.
     */
    private const EXPLICIT_PAGES = ['usersc/login.php', 'usersc/join.php'];

    /** @var list<string> Path substrings that exclude a file from discovery outright. */
    private const EXCLUDED_PATH_SUBSTRINGS = [
        '/vendor/',
        '/node_modules/',
        '/tests/',
        'usersc/plugins/ai_prompts/',
        'usersc/includes/example_',
        'users/_blank_pages/',
        'users/modules/',
        'users/views/',
        'users/helpers/',
        '_TEMPLATE_',
    ];

    /** Regex matching a genuine `securePage()` call site, not a mention in a comment or doc string. */
    private const SECURE_PAGE_CALL_PATTERN = '/if\s*\(\s*!\s*securePage\s*\(/';

    public function run(): void
    {
        $repoRoot = dirname(__DIR__, 2);
        $pages = $this->discoverPages($repoRoot);

        foreach ($pages as $page) {
            $pageId = $this->findPageId($page);

            if ($pageId !== null && $this->isRegistrationComplete($pageId, $page)) {
                continue;
            }

            $this->registerNewPage($page, $pageId);
        }
    }

    /**
     * A page row existing is not sufficient evidence that registration finished. This seed's own
     * `registerNewPage()` can't leave a page partially registered — it wraps both the `pages`
     * and `permission_page_matches` inserts in one transaction — but the `pages` row could have
     * come from somewhere else entirely: UserSpice's lazy `createPages()` firing before this seed
     * ever ran, a pre-#1671 version of this seed that predated the transaction wrap, or manual DB
     * intervention. Compare the actual match count against what classification expects for this
     * page so that kind of external drift is healed instead of permanently skipped.
     */
    private function isRegistrationComplete(int $pageId, string $page): bool
    {
        $expected = count($this->classifyPermissions($page));

        $row = $this->query(
            'SELECT COUNT(*) AS cnt FROM `permission_page_matches` WHERE `page_id` = ?',
            [$pageId]
        )->fetch(\PDO::FETCH_ASSOC);
        $actual = $row !== false ? (int) $row['cnt'] : 0;

        return $actual >= $expected;
    }

    private function registerNewPage(string $page, ?int $existingPageId): void
    {
        $permissionIds = $this->classifyPermissions($page);
        foreach ($permissionIds as $permissionId) {
            $this->assertPermissionExists($permissionId, $page);
        }

        $adapter = $this->getAdapter();
        $adapter->beginTransaction();

        try {
            $pageId = $existingPageId ?? $this->insertPage($page);

            foreach ($permissionIds as $permissionId) {
                $this->insertPermissionMatch($pageId, $permissionId);
            }

            $adapter->commitTransaction();
        } catch (\Throwable $e) {
            $adapter->rollbackTransaction();
            throw $e;
        }
    }

    private function insertPage(string $page): int
    {
        // `z_us_root.php` carries `core=1` on dev/prod (see the ROOT_PAGES docblock); every
        // other discovered page is `core=0`, matching what lazy registration would have inserted.
        $core = $page === 'z_us_root.php' ? 1 : 0;

        $inserted = $this->execute(
            'INSERT INTO `pages` (`page`, `title`, `private`, `re_auth`, `core`, `lang_key`) ' .
            'VALUES (?, NULL, ?, 0, ?, NULL)',
            [$page, $this->classifyPrivate($page), $core]
        );

        if ($inserted !== 1) {
            throw new RuntimeException(
                "PageRegistrationSeed: inserting `pages`.`page` = '{$page}' reported {$inserted} " .
                'affected rows, expected 1. Check for a duplicate-key conflict or a schema change ' .
                'to the `pages` table, then re-run the seed.'
            );
        }

        $pageId = $this->findPageId($page);
        if ($pageId === null) {
            throw new RuntimeException(
                "PageRegistrationSeed: `pages`.`page` = '{$page}' was just inserted but cannot be " .
                're-selected. This suggests a transaction visibility or connection issue — verify ' .
                'the seed is running against a single connection and re-run.'
            );
        }

        return $pageId;
    }

    /**
     * `permission_page_matches.permission_id` has no FK constraint, so an insert referencing a
     * nonexistent permission id would succeed silently and produce an orphaned, meaningless row.
     * The `RegisterBaselinePermissions` migration guarantees id 3 exists before any seed runs;
     * this check turns a broken environment into a loud failure instead of silent data
     * corruption.
     */
    private function assertPermissionExists(int $permissionId, string $page): void
    {
        $row = $this->query('SELECT id FROM `permissions` WHERE id = ?', [$permissionId])
            ->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new RuntimeException(
                "PageRegistrationSeed: page '{$page}' classifies to permission_id={$permissionId}, " .
                'but no such row exists in `permissions`. Run composer migrate ' .
                '(RegisterBaselinePermissions) before running this seed.'
            );
        }
    }

    private function findPageId(string $page): ?int
    {
        $row = $this->query('SELECT id FROM `pages` WHERE `page` = ?', [$page])->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? (int) $row['id'] : null;
    }

    /**
     * Walk the known page directories and return every repo-root-relative
     * path that should be registered — either because it genuinely calls
     * `securePage()` (`app/`, `usersc/`, `docs/`) or because it lives under
     * `users/`, where `getUserSpiceInstallerSpec()`'s own default already
     * determines the correct classification regardless of whether the file
     * calls `securePage()` itself.
     *
     * @return list<string>
     */
    private function discoverPages(string $repoRoot): array
    {
        $pages = [
            ...self::ROOT_PAGES,
            ...self::EXPLICIT_PAGES,
            ...$this->discoverSecurePageCallers($repoRoot),
            ...$this->discoverUserSpiceInstallerPages($repoRoot),
        ];

        sort($pages);

        return $pages;
    }

    /**
     * @return list<string> repo-root-relative paths under `app/`, `usersc/`, `docs/` that
     *                       genuinely call `securePage()`.
     */
    private function discoverSecurePageCallers(string $repoRoot): array
    {
        $pages = [];

        foreach (self::SECURE_PAGE_SCAN_DIRECTORIES as $directory) {
            foreach ($this->walkPhpFiles($repoRoot, $directory) as $relativePath) {
                $absolutePath = $repoRoot . '/' . $relativePath;
                $contents = file_get_contents($absolutePath);
                if ($contents === false) {
                    throw new RuntimeException(
                        "PageRegistrationSeed: unable to read '{$absolutePath}' during page discovery. " .
                        'Check file permissions and that the path still exists.'
                    );
                }

                if (preg_match(self::SECURE_PAGE_CALL_PATTERN, $contents) === 1) {
                    $pages[] = $relativePath;
                }
            }
        }

        return $pages;
    }

    /**
     * Every top-level `.php` file directly under `users/` (not recursive), unconditionally —
     * `getUserSpiceInstallerSpec()` already supplies the correct classification (including its
     * public-by-default fallback) whether or not the file calls `securePage()` itself. Every
     * routable UserSpice page (`login.php`, `admin.php`, `join.php`, etc.) lives directly under
     * `users/` — every subdirectory (`users/classes/`, `users/auth/`, `users/includes/`,
     * `users/lang/`, `users/parsers/`, `users/updates/components/`, `users/cron/`, ...) holds
     * libraries, includes, language packs, or upgrade scripts, never an independently routable
     * page. Recursing into those would misregister hundreds of non-pages as public (the fallback
     * in `getUserSpiceInstallerSpec()` defaults anything not in its explicit map to public).
     *
     * @return list<string>
     */
    private function discoverUserSpiceInstallerPages(string $repoRoot): array
    {
        return $this->walkPhpFiles($repoRoot, self::USERSPICE_INSTALLER_DIRECTORY, recursive: false);
    }

    /**
     * @return list<string> repo-root-relative, non-excluded `.php` file paths under $directory.
     */
    private function walkPhpFiles(string $repoRoot, string $directory, bool $recursive = true): array
    {
        $absoluteDirectory = $repoRoot . '/' . $directory;
        if (!is_dir($absoluteDirectory)) {
            return [];
        }

        $paths = [];

        $directoryIterator = new RecursiveDirectoryIterator($absoluteDirectory, FilesystemIterator::SKIP_DOTS);
        $iterator = $recursive ? new RecursiveIteratorIterator($directoryIterator) : $directoryIterator;

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), strlen($repoRoot) + 1);

            if (!$this->isExcludedPath($relativePath)) {
                $paths[] = $relativePath;
            }
        }

        return $paths;
    }

    private function isExcludedPath(string $relativePath): bool
    {
        foreach (self::EXCLUDED_PATH_SUBSTRINGS as $excluded) {
            if (str_contains($relativePath, $excluded)) {
                return true;
            }
        }

        return false;
    }

    private function classifyPrivate(string $page): int
    {
        if (str_starts_with($page, 'users/')) {
            $spec = PagePermissionClassifier::getUserSpiceInstallerSpec($page);

            return ($spec['private'] ?? 0) ? 1 : 0;
        }

        return PagePermissionClassifier::shouldBePrivate($page) ? 1 : 0;
    }

    /**
     * Determine which permission ids a newly-registered page should receive,
     * mirroring `21-Fix-Page-Permissions.php`'s bucketing exactly.
     *
     * @return list<int>
     */
    private function classifyPermissions(string $page): array
    {
        if (str_starts_with($page, 'users/')) {
            $spec = PagePermissionClassifier::getUserSpiceInstallerSpec($page);

            /** @var list<int> $perms */
            $perms = $spec['perms'] ?? [];

            return $perms;
        }

        $private = PagePermissionClassifier::shouldBePrivate($page);

        if (!$private) {
            return [];
        }

        if (!PagePermissionClassifier::shouldHaveAdminPermissions($page)) {
            return [self::PERM_USER];
        }

        if (PagePermissionClassifier::shouldBeAdminOnly($page)) {
            return [self::PERM_ADMIN];
        }

        return [self::PERM_ADMIN, self::PERM_EDITOR];
    }

    private function insertPermissionMatch(int $pageId, int $permissionId): void
    {
        $existing = $this->query(
            'SELECT id FROM `permission_page_matches` WHERE `page_id` = ? AND `permission_id` = ?',
            [$pageId, $permissionId]
        )->fetch(\PDO::FETCH_ASSOC);

        if ($existing !== false) {
            return;
        }

        $inserted = $this->execute(
            'INSERT INTO `permission_page_matches` (`permission_id`, `page_id`) VALUES (?, ?)',
            [$permissionId, $pageId]
        );

        if ($inserted !== 1) {
            throw new RuntimeException(
                "PageRegistrationSeed: inserting permission_page_matches for page_id={$pageId}, " .
                "permission_id={$permissionId} reported {$inserted} affected rows, expected 1. " .
                'Check for a schema change to `permission_page_matches`, then re-run the seed.'
            );
        }
    }
}
