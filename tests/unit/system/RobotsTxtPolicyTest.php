<?php

declare(strict_types=1);

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
}
