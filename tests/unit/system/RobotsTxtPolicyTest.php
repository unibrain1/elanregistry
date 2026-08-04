<?php

declare(strict_types=1);

use ElanRegistry\SitemapService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for Issue #1413.
 *
 * robots.txt previously gave every named AI crawler a uniform, unconditional
 * `Disallow: /` — nothing to regress. Issue #1413 replaced that with a single
 * shared `User-agent` group carrying real Allow/Disallow precedence logic
 * (longest-prefix-match wins, per RFC 9309 / Google's documented algorithm),
 * so the group's claims match what /llms.txt tells AI crawlers they may
 * index. This is the first order-dependent policy robots.txt has ever had in
 * this repo, so unlike the old file, a future edit (reordered lines, a typo
 * in one of the path overrides, a broadened Allow without a matching
 * re-Disallow) can now silently change what's actually reachable.
 *
 * This test reads the raw file via file_get_contents() (never requires or
 * executes it) and reimplements just enough of the longest-prefix-match
 * algorithm to check policy outcomes for a representative bot — it verifies
 * the *policy*, not that any given crawler actually honors robots.txt.
 */
#[Group('system')]
class RobotsTxtPolicyTest extends TestCase
{
    private const ROBOTS_FILE = 'robots.txt';
    private const ROBOTS_TEST_FILE = 'robots-test.txt';
    private const LLMS_FILE = 'llms.txt';
    private const LLMS_TEST_FILE = 'llms-test.txt';

    /** A crawler with its own dedicated group in the AI-bot policy — representative of all 17. */
    private const AI_BOT = 'GPTBot';

    private string $rootDir = '';

    /** @var array<int, array{agents: list<string>, rules: list<array{type: string, path: string}>}> */
    private array $groups = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->rootDir = dirname(__DIR__, 3);

        $filePath = $this->rootDir . '/' . self::ROBOTS_FILE;
        $this->assertFileExists($filePath, self::ROBOTS_FILE . ' must exist (Issue #1413)');

        $this->groups = $this->parseRobotsTxt((string)file_get_contents($filePath));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function pathAllowanceProvider(): array
    {
        return [
            // Allowed: public car listings and docs — what /llms.txt claims AI crawlers may index.
            'car detail page' => ['/app/owner/cars/details.php?car_id=1', true],
            'docs hub' => ['/docs/', true],
            'nested reference doc' => ['/docs/reference/paint-colors.php', true],
            'car histories' => ['/docs/stories/', true],
            // Blocked: the override-wins case — most likely to silently regress.
            'login-gated edit page' => ['/app/owner/cars/edit.php', false],
            'owner contact form' => ['/app/owner/contact/', false],
            // Blocked: unrelated paths fall through to the catch-all Disallow: /.
            'admin area' => ['/app/admin/', false],
            'api endpoint' => ['/app/api/cars/list.php', false],
        ];
    }

    /**
     * Verifies the shared AI-bot group's Allow/Disallow precedence for a
     * representative crawler against the paths /llms.txt claims are open.
     */
    #[DataProvider('pathAllowanceProvider')]
    public function testAiBotPathAllowance(string $path, bool $expectedAllowed): void
    {
        $isAllowed = $this->isAllowed(self::AI_BOT, $path);

        $this->assertSame(
            $expectedAllowed,
            $isAllowed,
            sprintf(
                "%s must be %s for %s (Issue #1413)",
                $path,
                $expectedAllowed ? 'allowed' : 'disallowed',
                self::AI_BOT
            )
        );
    }

    /**
     * The new AI-bot group must not alter the default (*) group's existing
     * exclusions for non-AI crawlers.
     */
    public function testDefaultUserAgentGroupIsUnchanged(): void
    {
        // The AI-bot policy change must not touch the default (*) group's
        // existing exclusions for non-AI crawlers.
        $this->assertFalse($this->isAllowed('SomeSearchEngine', '/users/'));
        $this->assertFalse($this->isAllowed('SomeSearchEngine', '/app/admin/'));
        $this->assertFalse($this->isAllowed('SomeSearchEngine', '/app/owner/cars/edit.php'));
        $this->assertTrue($this->isAllowed('SomeSearchEngine', '/app/owner/cars/details.php?car_id=1'));
    }

    /**
     * Every static page SitemapService (#1373) advertises to crawlers must
     * actually be reachable per robots.txt's default (search-engine) group —
     * otherwise Google Search Console reports "Submitted URL blocked by
     * robots.txt", the exact class of noise this milestone (#1373 + #1413)
     * set out to eliminate. Reads SitemapService::STATIC_PAGES via
     * reflection (rather than duplicating the path list here) so this test
     * can't silently drift from the actual sitemap content.
     */
    public function testSitemapStaticPagesAreCrawlable(): void
    {
        $reflection = new \ReflectionClass(SitemapService::class);
        /** @var list<array{path: string, changefreq: string, priority: string}> $staticPages */
        $staticPages = $reflection->getConstant('STATIC_PAGES');
        $this->assertNotEmpty($staticPages, 'SitemapService::STATIC_PAGES must be non-empty for this test to be meaningful');

        foreach ($staticPages as $page) {
            $this->assertTrue(
                $this->isAllowed('Googlebot', $page['path']),
                "{$page['path']} is listed in SitemapService::STATIC_PAGES but blocked by robots.txt's " .
                    "default group — Google would report \"Submitted URL blocked by robots.txt\" for this sitemap entry"
            );
        }
    }

    /**
     * llms.txt (#1413) is designed to be a self-contained summary an LLM
     * doesn't need to cross-reference against robots.txt — so its Allow/
     * Disallow claims must actually be true of the AI-bot group's enforced
     * policy, not just happen to look similar today. This is a semantic
     * consistency check, not exact-set equality: llms.txt may legitimately
     * list a path (e.g. `/docs/stories/`) that robots.txt covers only via a
     * broader Allow rule (`/docs/`) — what matters is that every path
     * llms.txt claims is open really is allowed, and every path it claims
     * is closed really is blocked, per the actual enforced robots.txt policy.
     */
    public function testLlmsTxtAgreesWithRobotsTxtAiBotPolicy(): void
    {
        $llmsPath = $this->rootDir . '/' . self::LLMS_FILE;
        $this->assertFileExists($llmsPath, self::LLMS_FILE . ' must exist (Issue #1413)');

        $llms = $this->parseLlmsTxt((string)file_get_contents($llmsPath));
        $this->assertNotEmpty($llms['allow'], 'llms.txt ## Allow section must list at least one path');
        $this->assertNotEmpty($llms['disallow'], 'llms.txt ## Disallow section must list at least one path');

        foreach ($llms['allow'] as $path) {
            $this->assertTrue(
                $this->isAllowed(self::AI_BOT, $path),
                "llms.txt claims $path is allowed, but robots.txt's AI-bot group blocks it (Issue #1413)"
            );
        }
        foreach ($llms['disallow'] as $path) {
            $this->assertFalse(
                $this->isAllowed(self::AI_BOT, $path),
                "llms.txt claims $path is disallowed, but robots.txt's AI-bot group allows it (Issue #1413)"
            );
        }
    }

    /**
     * scripts/server-hooks/post-receive swaps these files over the
     * production robots.txt/llms.txt on every Test-environment deploy —
     * test.elanregistry.org has no basic auth and relies entirely on this
     * swap to stay unindexed. If either tracked source file is ever
     * deleted or renamed, that swap silently fails (robots.txt) or halts
     * the deploy (llms.txt) with no CI signal today.
     */
    public function testTestEnvironmentLockdownFilesExist(): void
    {
        $this->assertFileExists(
            $this->rootDir . '/' . self::ROBOTS_TEST_FILE,
            self::ROBOTS_TEST_FILE . ' must exist — post-receive swaps it over robots.txt on Test deploys'
        );
        $this->assertFileExists(
            $this->rootDir . '/' . self::LLMS_TEST_FILE,
            self::LLMS_TEST_FILE . ' must exist — post-receive swaps it over llms.txt on Test deploys (Issue #1413)'
        );
    }

    /**
     * Longest-prefix-match wins (RFC 9309 §2.2.2): among all Allow/Disallow
     * rules in the bot's matching group whose path prefixes the URL, the
     * longest one applies. A crawler with no dedicated group falls back to
     * `User-agent: *`. Absent any matching rule, the path is allowed.
     *
     * Simplification: on an exact tie in prefix length, this keeps whichever
     * rule appears first in the file, rather than Google's documented
     * "least-restrictive-wins" tie-break. No path/rule combination in the
     * current robots.txt actually ties, so this doesn't affect the
     * assertions above — but it means this helper is not a full RFC 9309
     * tie-break implementation, and a future robots.txt edit that introduces
     * an equal-length Allow/Disallow pair could pass here while behaving
     * differently for a real crawler.
     */
    private function isAllowed(string $userAgent, string $path): bool
    {
        $group = $this->findGroup($userAgent) ?? $this->findGroup('*');
        $this->assertNotNull($group, "No matching group (not even '*') found for $userAgent");

        $bestLength = -1;
        $bestType = 'allow';
        foreach ($group['rules'] as $rule) {
            if ($rule['path'] === '' || strncmp($path, $rule['path'], strlen($rule['path'])) !== 0) {
                continue;
            }
            $length = strlen($rule['path']);
            if ($length > $bestLength) {
                $bestLength = $length;
                $bestType = $rule['type'];
            }
        }

        return $bestType !== 'disallow';
    }

    /**
     * @return array{agents: list<string>, rules: list<array{type: string, path: string}>}|null
     */
    private function findGroup(string $userAgent): ?array
    {
        foreach ($this->groups as $group) {
            if (in_array($userAgent, $group['agents'], true)) {
                return $group;
            }
        }
        return null;
    }

    /**
     * @return array<int, array{agents: list<string>, rules: list<array{type: string, path: string}>}>
     */
    private function parseRobotsTxt(string $content): array
    {
        /** @var array<int, array{agents: list<string>, rules: list<array{type: string, path: string}>}> $groups */
        $groups = [];
        /** @var list<string> $pendingAgents */
        $pendingAgents = [];
        /** @var list<array{type: string, path: string}> $currentRules */
        $currentRules = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = trim((string)$line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^User-agent:\s*(.+)$/i', $line, $matches) === 1) {
                // A User-agent line after directives have already been seen for the
                // current group starts a NEW group (RFC 9309 §2.2.1).
                if ($currentRules !== []) {
                    $groups[] = ['agents' => $pendingAgents, 'rules' => $currentRules];
                    $pendingAgents = [];
                    $currentRules = [];
                }
                $pendingAgents[] = trim($matches[1]);
                continue;
            }

            if (preg_match('/^(Allow|Disallow):\s*(.*)$/i', $line, $matches) === 1) {
                $currentRules[] = ['type' => strtolower($matches[1]), 'path' => trim($matches[2])];
            }
        }
        if ($pendingAgents !== []) {
            $groups[] = ['agents' => $pendingAgents, 'rules' => $currentRules];
        }

        return $groups;
    }

    /**
     * Parses llms.txt's `## Allow` / `## Disallow` markdown sections into
     * flat path lists. Each entry is a `- /path/ — description` bullet;
     * only the path (up to the first whitespace or em-dash) is extracted.
     *
     * @return array{allow: list<string>, disallow: list<string>}
     */
    private function parseLlmsTxt(string $content): array
    {
        $allow = [];
        $disallow = [];
        $currentSection = null;

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = trim((string)$line);

            if (preg_match('/^##\s*(Allow|Disallow)\s*$/i', $line, $matches) === 1) {
                $currentSection = strtolower($matches[1]);
                continue;
            }
            if ($line !== '' && str_starts_with($line, '#')) {
                // A different heading (e.g. a new ## section, or the H1 title) ends
                // the current Allow/Disallow list.
                $currentSection = null;
                continue;
            }
            if ($currentSection === null || !str_starts_with($line, '-')) {
                continue;
            }

            if (preg_match('/^-\s*(\S+)/', $line, $matches) === 1) {
                if ($currentSection === 'allow') {
                    $allow[] = $matches[1];
                } else {
                    $disallow[] = $matches[1];
                }
            }
        }

        return ['allow' => $allow, 'disallow' => $disallow];
    }
}
