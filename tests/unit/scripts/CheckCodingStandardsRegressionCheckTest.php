<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Coverage for the numbered-vs-descriptive filename branch added to
 * checkRegressionTestStructure() in scripts/check-coding-standards.php by
 * #1559 (which also made the method reachable for the first time — see its
 * own docblock). Runs the real script as a subprocess against small fixture
 * files rather than reaching into its private methods via Reflection, so it
 * exercises the exact CLI invocation .githooks/pre-commit and CI actually use.
 */
final class CheckCodingStandardsRegressionCheckTest extends TestCase
{
    private string $checkerScript;

    protected function setUp(): void
    {
        $this->checkerScript = dirname(__DIR__, 3) . '/scripts/check-coding-standards.php';
    }

    /**
     * A numbered file (Issue{N}RegressionTest), a descriptive file
     * ({Name}RegressionTest), and the RegressionTestCase base-class
     * exemption must all pass with zero errors.
     */
    public function testValidFixturesProduceNoErrors(): void
    {
        $root = $this->writeFixtures([
            'Issue9001RegressionTest.php' => <<<'PHP'
                <?php

                declare(strict_types=1);

                use PHPUnit\Framework\TestCase;

                /**
                 * @issue 9001
                 * @link https://github.com/elan-registry/registry/issues/9001
                 */
                final class Issue9001RegressionTest extends TestCase
                {
                    public function testIssue9001_Placeholder(): void
                    {
                        $this->assertTrue(true);
                    }
                }
                PHP,
            'SampleThingRegressionTest.php' => <<<'PHP'
                <?php

                declare(strict_types=1);

                use PHPUnit\Framework\TestCase;

                /**
                 * @issue 9003
                 * @link https://github.com/elan-registry/registry/issues/9003
                 */
                final class SampleThingRegressionTest extends TestCase
                {
                    public function testSomething(): void
                    {
                        $this->assertTrue(true);
                    }
                }
                PHP,
            'RegressionTestCase.php' => <<<'PHP'
                <?php

                declare(strict_types=1);

                use PHPUnit\Framework\TestCase;

                abstract class RegressionTestCase extends TestCase
                {
                }
                PHP,
        ]);

        [$exitCode, $output] = $this->runChecker($root);

        $this->assertSame(0, $exitCode, "Expected no errors, got:\n" . $output);
        $this->assertStringContainsString('Errors: 0', $output);
    }

    /**
     * A numbered file missing its @issue/@link annotations, and a file that
     * matches neither the numbered nor descriptive filename pattern, must
     * both be reported as blocking errors.
     */
    public function testInvalidFixturesAreReportedAsErrors(): void
    {
        $root = $this->writeFixtures([
            'Issue9002RegressionTest.php' => <<<'PHP'
                <?php

                declare(strict_types=1);

                use PHPUnit\Framework\TestCase;

                final class Issue9002RegressionTest extends TestCase
                {
                    public function testSomething(): void
                    {
                        $this->assertTrue(true);
                    }
                }
                PHP,
            'NotAValidRegressionName.php' => <<<'PHP'
                <?php

                declare(strict_types=1);

                use PHPUnit\Framework\TestCase;

                final class NotAValidRegressionName extends TestCase
                {
                    public function testSomething(): void
                    {
                        $this->assertTrue(true);
                    }
                }
                PHP,
        ]);

        [$exitCode, $output] = $this->runChecker($root);

        $this->assertSame(1, $exitCode, "Expected blocking errors, got:\n" . $output);
        $this->assertStringContainsString('Missing @issue annotation', $output);
        $this->assertStringContainsString('Regression test filename must follow pattern', $output);
    }

    /**
     * Writes each [filename => content] pair into a fresh temp directory's
     * tests/unit/regression/ subtree and returns the temp root.
     *
     * @param array<string, string> $fixtures
     */
    private function writeFixtures(array $fixtures): string
    {
        $root = sys_get_temp_dir() . '/check-coding-standards-test-' . uniqid();
        $regressionDir = $root . '/tests/unit/regression';
        mkdir($regressionDir, 0777, true);

        foreach ($fixtures as $filename => $content) {
            file_put_contents($regressionDir . '/' . $filename, $content . "\n");
        }

        return $root;
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runChecker(string $root): array
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->checkerScript)
            . ' ' . escapeshellarg($root) . ' 2>&1';
        exec($cmd, $outputLines, $exitCode);

        $this->removeDirectory($root);

        return [$exitCode, implode("\n", $outputLines)];
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
