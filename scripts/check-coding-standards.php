<?php

declare(strict_types=1);

/**
 * Coding Standards Checker
 *
 * Local script to check for common coding standard violations before creating PRs.
 * Helps prevent blocking issues in Claude Code Review and other automated checks.
 *
 * Usage:
 *   php scripts/check-coding-standards.php [directory]           # Normal mode (warnings as info)
 *   php scripts/check-coding-standards.php [directory] --strict  # Strict mode (warnings block)
 *   php scripts/check-coding-standards.php [directory] --staged  # Check staged files only
 *   php scripts/check-coding-standards.php [directory] --verbose # Show all warning details
 *
 * @author Elan Registry Development Team
 */

class CodingStandardsChecker
{
    /**
     * Path to the phpstan.neon config this checker sources its project-owned
     * paths list from. Always the repo's own copy — resolved relative to this
     * script's fixed location, not the directory being scanned, so it's found
     * correctly even when scanning a subdirectory or the pre-commit hook's
     * temp copy of staged files.
     */
    private const PHPSTAN_CONFIG = __DIR__ . '/../phpstan.neon';

    /**
     * Project-owned paths, relative to the repository root. Loaded from
     * phpstan.neon's `parameters.paths` list (see loadProjectPaths()) so this
     * checker and PHPStan share one source of truth for which paths count as
     * project code. Note: only `paths` is read, not `excludePaths` — the two
     * tools' exclusions (see checkDirectory()'s early-skip list) are
     * maintained separately and can still drift.
     *
     * @var array<int, string>
     */
    private array $projectPaths;

    private array $errors = [];
    private array $warnings = [];
    private int $filesChecked = 0;
    private bool $strictMode = false;
    private bool $verboseMode = false;

    public function __construct()
    {
        $this->projectPaths = $this->loadProjectPaths(self::PHPSTAN_CONFIG);
    }

    /**
     * Parse the `parameters.paths` list out of phpstan.neon.
     *
     * This is a minimal line-based reader, not a general NEON parser — it
     * relies on phpstan.neon's `paths:` block being a plain list of `- entry`
     * lines at a fixed indentation (as it is today). That's a deliberate
     * trade-off to avoid pulling in a NEON/YAML dependency for one config
     * file; if phpstan.neon's paths block formatting changes, update this
     * alongside it.
     *
     * @param string $configPath Path to phpstan.neon
     * @return array<int, string>
     */
    private function loadProjectPaths(string $configPath): array
    {
        if (!is_file($configPath)) {
            throw new RuntimeException(
                "Cannot find phpstan.neon at $configPath — this checker reads its " .
                "project-owned paths list from it and cannot run without it."
            );
        }

        $lines = file($configPath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException("Failed to read $configPath");
        }

        $paths = [];
        $inPathsBlock = false;

        foreach ($lines as $line) {
            if (preg_match('/^\s{4}paths:\s*$/', $line)) {
                $inPathsBlock = true;
                continue;
            }

            if (!$inPathsBlock) {
                continue;
            }

            // A new top-level parameter at the same indentation as `paths:`
            // (e.g. `excludePaths:`) ends the block. Matched narrowly (an
            // identifier followed by `:`) so a 4-space-indented comment line
            // inside the paths block doesn't falsely end it early.
            if (preg_match('/^\s{4}[\w-]+:/', $line)) {
                break;
            }

            if (preg_match('/^\s{8}-\s*(\S+)/', $line, $matches)) {
                $paths[] = $matches[1];
            }
        }

        if ($paths === []) {
            throw new RuntimeException(
                "Parsed zero paths from $configPath's paths: block — the file's " .
                "format may have changed. See loadProjectPaths()'s docblock."
            );
        }

        return $paths;
    }

    /**
     * Main execution method
     *
     * @param array $args Command line arguments
     * @return int Exit code (0 = success, 1 = issues found)
     */
    public function run(array $args): int
    {
        $directory = $args[1] ?? '.';
        $isStaged = in_array('--staged', $args);
        $this->strictMode = in_array('--strict', $args);
        $this->verboseMode = in_array('--verbose', $args);

        if ($isStaged) {
            echo "🔍 Checking coding standards for staged files in: $directory\n";
        } else {
            echo "🔍 Checking coding standards in: $directory\n";
        }

        if ($this->strictMode) {
            echo "📋 Mode: STRICT (warnings will block)\n";
        }

        echo "=" . str_repeat("=", 50) . "\n\n";

        $this->checkDirectory($directory);

        $this->printResults($isStaged);

        // A CI gate (.github/workflows/static-analysis.yml) and the pre-commit
        // hook both invoke this against a directory that should always contain
        // at least one project-owned PHP file. Zero files checked means path
        // resolution silently filtered everything out — that must not read as
        // a pass.
        if ($this->filesChecked === 0) {
            echo "\n❌ No PHP files were scanned in $directory.\n";
            echo "   Either it contains no project-owned PHP, or path resolution failed ";
            echo "(e.g. a scan directory nested inside the repo). Not treating this as a pass.\n";
            return 1;
        }

        // In strict mode, warnings also cause failure
        if ($this->strictMode) {
            return (count($this->errors) > 0 || count($this->warnings) > 0) ? 1 : 0;
        }

        return count($this->errors) > 0 ? 1 : 0;
    }

    /**
     * Check all PHP files in directory recursively
     *
     * @param string $directory Directory to check
     * @return void
     */
    private function checkDirectory(string $directory): void
    {
        $root = $this->resolveProjectRoot($directory);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                // Fast early-skip for vendor, third-party, and test code
                $path = $file->getPathname();
                if (strpos($path, '/vendor/') !== false ||
                    strpos($path, '/node_modules/') !== false ||
                    strpos($path, '/scripts/fix/_ARCHIVE/') !== false ||
                    strpos($path, '/tests/') !== false ||
                    strpos($path, '/.git/') !== false) {
                    continue;
                }

                // Only scan project-owned paths (see loadProjectPaths())
                if (!$this->isProjectOwnedPath($path, $root)) {
                    continue;
                }

                $this->checkFile($path);
            }
        }
    }

    /**
     * Resolve the repo root $this->projectPaths entries are relative to.
     *
     * Walks upward from $directory looking for composer.json so that scanning
     * a subdirectory (e.g. `usersc/classes`) still resolves paths against the
     * real repo root instead of the subdirectory itself. Falls back to
     * $directory when no composer.json is found upward — this is what makes
     * the pre-commit hook's `--staged` mode work, since its temp directory
     * mirrors the repo's relative structure starting at its own root and has
     * no composer.json of its own.
     *
     * @param string $directory Directory the scan was started from
     * @return string Resolved root directory
     */
    private function resolveProjectRoot(string $directory): string
    {
        $start = realpath($directory) ?: rtrim($directory, '/');
        $probe = is_dir($start) ? $start : dirname($start);

        while ($probe !== '' && $probe !== DIRECTORY_SEPARATOR) {
            if (is_file($probe . '/composer.json')) {
                return $probe;
            }

            $parent = dirname($probe);
            if ($parent === $probe) {
                break;
            }

            $probe = $parent;
        }

        return $start;
    }

    /**
     * Determine whether a file lives under a project-owned path
     *
     * @param string $filePath Path to the file being considered
     * @param string $root Already-resolved root directory (see resolveProjectRoot())
     * @return bool True if the file is project-owned and should be checked
     */
    private function isProjectOwnedPath(string $filePath, string $root): bool
    {
        $absolute = realpath($filePath) ?: $filePath;

        $prefix = rtrim($root, '/') . '/';
        if (strpos($absolute, $prefix) !== 0) {
            return false;
        }

        $relative = substr($absolute, strlen($prefix));

        foreach ($this->projectPaths as $allowed) {
            if ($relative === $allowed || strpos($relative, $allowed . '/') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Truncate $content at the real __halt_compiler() statement, if any.
     *
     * Uses the tokenizer rather than a substring/regex search so a comment or
     * string literal that merely mentions the token text doesn't trigger a
     * truncation — only an actual T_HALT_COMPILER token does.
     *
     * @param string $content File content
     * @return string Content up to (excluding) the halt statement, or the
     *                 original content unchanged if there isn't one
     */
    private function truncateAtHaltCompiler(string $content): string
    {
        $offset = 0;
        foreach (@token_get_all($content) as $token) {
            if (is_array($token) && $token[0] === T_HALT_COMPILER) {
                return substr($content, 0, $offset);
            }
            $offset += strlen(is_array($token) ? $token[1] : $token);
        }

        return $content;
    }

    /**
     * Check individual PHP file for coding standards
     *
     * @param string $filePath Path to PHP file
     * @return void
     */
    private function checkFile(string $filePath): void
    {
        $this->filesChecked++;
        $content = file_get_contents($filePath);

        if ($content === false) {
            $this->warnings[] = "$filePath: unreadable, skipped";
            return;
        }

        // Everything after __halt_compiler() is inert data (e.g. the raw
        // markdown in usersc/plugins/ai_prompts/custom_prompts/*.md.php),
        // not live PHP — do not scan it. Locate the real token rather than a
        // substring match, so a comment or string merely mentioning
        // "__halt_compiler()" (like this one) can't truncate the file's own
        // scan.
        $content = $this->truncateAtHaltCompiler($content);

        $lines = explode("\n", $content);

        // =================================================================
        // TIER 1: BLOCKING CHECKS (Always enforced - catch real bugs/security issues)
        // =================================================================

        // Type safety (catches real bugs)
        $this->checkStrictTypes($filePath, $content);
        $this->checkFunctionTypes($filePath, $lines);

        // Documentation (API clarity)
        $this->checkPHPDocBlocks($filePath, $lines);

        // Security checks (critical)
        $this->checkCSRFProtection($filePath, $content);
        $this->checkSQLSecurity($filePath, $content);
        $this->checkInputValidation($filePath, $content);

        // Architecture checks (code quality)
        $this->checkExceptionHandling($filePath, $content);

        // Regression test validation (traceability)
        $this->checkRegressionTestStructure($filePath, $content, $lines);

        // =================================================================
        // TIER 2: WARNING CHECKS (Advisory - contextual issues)
        // Only run in strict mode or verbose mode to reduce noise
        // =================================================================

        if ($this->strictMode || $this->verboseMode) {
            $this->checkNPlusOneQueries($filePath, $content);
        }

        // NOTE: Removed overly noisy checks that generated false positives:
        // - checkDatabaseTypeCasting() - Too many false positives
        // - checkCachingOpportunities() - Too contextual
        // - checkErrorHandling() for json/file ops - Not always needed
        // - checkPHPDocCompleteness() - Types already enforce this

        echo ".";
        if ($this->filesChecked % 50 === 0) {
            echo " $this->filesChecked\n";
        }
    }

    /**
     * Check for strict types declaration
     *
     * @param string $filePath File path for error reporting
     * @param string $content File content
     * @return void
     */
    private function checkStrictTypes(string $filePath, string $content): void
    {
        // Skip if it's not a new file or if it's a template/include file
        if (strpos($content, 'declare(strict_types=1)') === false) {
            // Strip <script> blocks to avoid matching JavaScript functions
            $phpOnly = $this->stripScriptBlocks($content);

            // Only flag if it contains PHP class definitions or PHP function declarations
            if (preg_match('/^\s*(abstract\s+|final\s+)?class\s+\w+/m', $phpOnly) ||
                preg_match('/^\s*(public|private|protected)\s+function\s+\w+/m', $phpOnly) ||
                preg_match('/^function\s+\w+/m', $phpOnly)) {
                $this->errors[] = "$filePath: Missing declare(strict_types=1) declaration";
            }
        }
    }

    // NOTE: checkDatabaseTypeCasting() removed - generated too many false positives.
    // Type casting issues are better caught by PHPStan static analysis.

    /**
     * Check function and method type declarations
     *
     * @param string $filePath File path for error reporting
     * @param array $lines File lines
     * @return void
     */
    private function checkFunctionTypes(string $filePath, array $lines): void
    {
        $inScript = false;

        foreach ($lines as $lineNum => $line) {
            $lineNumber = $lineNum + 1;

            // Track <script> blocks to skip JavaScript functions
            if (preg_match('/<script\b/i', $line)) {
                $inScript = true;
            }
            if (preg_match('/<\/script>/i', $line)) {
                $inScript = false;
                continue;
            }
            if ($inScript) {
                continue;
            }

            // Check for functions without return types (skip constructors - they can't have return types)
            if (preg_match('/\bfunction\s+(\w+)\s*\([^)]*\)/', $line, $funcMatches)) {
                if ($funcMatches[1] !== '__construct' && $funcMatches[1] !== '__destruct') {
                    if (!preg_match('/\)\s*:\s*(void|int|string|bool|array|object|float|self|static|mixed|never|\?\w+|\w+(\|\w+)*)/', $line)) {
                        $this->errors[] = "$filePath:$lineNumber: Function missing return type declaration";
                    }
                }
            }

            // Check for function parameters without types (including those with defaults)
            if (preg_match('/function\s+\w+\s*\(([^)]*)\)/', $line, $paramMatches) && !empty(trim($paramMatches[1]))) {
                $params = array_map('trim', explode(',', $paramMatches[1]));
                foreach ($params as $param) {
                    // A typed param starts with a type (e.g., "int $x", "?string $y", "Foo|Bar $z")
                    // An untyped param starts directly with $ (e.g., "$x", "$x = null")
                    if (preg_match('/^\$/', $param)) {
                        $this->warnings[] = "$filePath:$lineNumber: Function parameter may be missing type declaration";
                        break; // One warning per function is enough
                    }
                }
            }
        }
    }

    /**
     * Check for PHPDoc blocks on public methods
     *
     * @param string $filePath File path for error reporting
     * @param array $lines File lines
     * @return void
     */
    private function checkPHPDocBlocks(string $filePath, array $lines): void
    {
        $inClass = false;
        $inScript = false;
        $lastDocBlockEnd = -10; // Track where last docblock ended

        foreach ($lines as $lineNum => $line) {
            $lineNumber = $lineNum + 1;

            // Track <script> blocks to skip JavaScript
            if (preg_match('/<script\b/i', $line)) {
                $inScript = true;
            }
            if (preg_match('/<\/script>/i', $line)) {
                $inScript = false;
                continue;
            }
            if ($inScript) {
                continue;
            }

            if (preg_match('/^class\s+\w+/', trim($line))) {
                $inClass = true;
                continue;
            }

            // Track DocBlock end
            if (preg_match('/^\s*\*\//', $line)) {
                $lastDocBlockEnd = $lineNum;
                continue;
            }

            if ($inClass && preg_match('/^\s*public\s+function\s+(\w+)/', $line, $matches)) {
                $functionName = $matches[1];

                // Check if there's a recent DocBlock (within 5 lines, accounting for blank lines)
                $hasRecentDocBlock = ($lineNum - $lastDocBlockEnd) <= 5;

                if (!$hasRecentDocBlock && $functionName !== '__construct') {
                    $this->errors[] = "$filePath:$lineNumber: Public method '$functionName' missing PHPDoc block";
                }
            }
        }
    }

    /**
     * Check for CSRF protection in forms
     *
     * @param string $filePath File path for error reporting
     * @param string $content File content
     * @return void
     */
    private function checkCSRFProtection(string $filePath, string $content): void
    {
        // Pure accessor/utility classes are not form-processing endpoints; CSRF
        // validation is the caller's responsibility. Confirmed false positive — #1489.
        if (str_ends_with($filePath, 'usersc/classes/Input.php')) {
            return;
        }

        // Check if file contains form processing but no CSRF validation
        if (preg_match('/\$_POST\[/', $content) &&
            !preg_match('/Token::check\(/', $content) &&
            strpos($content, 'CSRF') === false) {
            $this->warnings[] = "$filePath: Form processing detected but no CSRF validation found";
        }
    }

    /**
     * Check for potential SQL injection vulnerabilities
     *
     * @param string $filePath File path for error reporting
     * @param string $content File content
     * @return void
     */
    private function checkSQLSecurity(string $filePath, string $content): void
    {
        // Check for potential SQL injection patterns - variable concatenation inside query string
        // This detects variables embedded directly in query strings (unsafe)
        // versus using prepared statements with placeholders (safe).
        //
        // Deliberately scoped to literal $db->query(...) rather than any
        // ->query(...) call: broadening this to match $this->db->query(...)
        // was tried and reverted — it produced blocking false positives on
        // usersc/classes/admin/BackupManager.php (table name interpolated
        // from a regex-validated allowlist, not user input — can't be
        // parameterized via ? placeholders at all) and
        // usersc/classes/Car/CarRepository.php's distinctCarModelValues()
        // (private, literal-only call sites). Neither is a real
        // vulnerability. This narrower detector only under-catches a
        // theoretical case (unsafe $this->db->query() concatenation in a
        // file that also has an unrelated properly-parameterized ->query()
        // call elsewhere) that doesn't currently exist in this codebase —
        // see #1489.
        if (preg_match('/\$db\s*->\s*query\s*\(\s*["\'][^"\']*\$[^"\']*["\']/', $content)) {
            $this->errors[] = "$filePath: Potential SQL injection - variable concatenation in query";
        }

        // The negation recognises any ->query() call (e.g. $db->query,
        // $this->db->query) whose second argument is an inline array
        // (short [] or legacy array(...) syntax) or a pre-built params
        // variable — all are prepared-statement usage.
        if (preg_match('/SELECT.*\$\w+/', $content) &&
            !preg_match('/->\s*query\s*\([^,]+,\s*(\[|array\s*\(|\$\w+)/', $content)) {
            $this->warnings[] = "$filePath: SQL query may not be using prepared statements";
        }
    }

    /**
     * Check for input validation and sanitization
     *
     * @param string $filePath File path for error reporting
     * @param string $content File content
     * @return void
     */
    private function checkInputValidation(string $filePath, string $content): void
    {
        // Check for direct use of superglobals without validation
        $superglobals = ['$_POST', '$_GET', '$_REQUEST', '$_COOKIE', '$_SESSION', '$_FILES'];

        foreach ($superglobals as $global) {
            if (preg_match('/' . preg_quote($global, '/') . '\[/', $content)) {
                // Check if there's any validation nearby
                if (!preg_match('/filter_var|htmlspecialchars|strip_tags|is_numeric|is_string|empty\(|isset\(/', $content)) {
                    $this->warnings[] = "$filePath: Direct use of $global detected - ensure proper validation and sanitization";
                }
            }
        }

        // Check for direct output of user input (XSS vulnerability)
        if (preg_match('/echo\s+.*\$_(GET|POST|REQUEST)\[/', $content)) {
            $this->errors[] = "$filePath: Direct output of user input - XSS vulnerability, use htmlspecialchars()";
        }

        // Check for file upload without validation
        if (preg_match('/move_uploaded_file\(.*\$_FILES/', $content) &&
            !preg_match('/\.(jpg|jpeg|png|gif)\$.*i/', $content)) {
            $this->warnings[] = "$filePath: File upload detected - ensure proper file type and size validation";
        }

        // Check for email operations without validation
        if (preg_match('/mail\(.*\$_(GET|POST)/', $content)) {
            $this->errors[] = "$filePath: Email function with user input - validate email addresses and sanitize content";
        }
    }

    /**
     * Check for proper exception handling
     *
     * @param string $filePath File path for error reporting
     * @param string $content File content
     * @return void
     */
    private function checkExceptionHandling(string $filePath, string $content): void
    {
        // Check for generic Exception throws
        if (preg_match('/throw\s+new\s+Exception\(/', $content)) {
            $this->errors[] = "$filePath: Using generic Exception - create specific exception classes instead";
        }

        if (preg_match('/throw\s+new\s+RuntimeException\(/', $content)) {
            $this->warnings[] = "$filePath: Using RuntimeException - consider more specific exception types";
        }

        // Check for Exception in catch blocks (too generic)
        if (preg_match('/catch\s*\(\s*Exception\s+/', $content)) {
            $this->warnings[] = "$filePath: Catching generic Exception - catch specific exception types when possible";
        }
    }

    // NOTE: checkErrorHandling() removed - too many false positives.
    // - Database operations: DB class already handles exceptions internally
    // - File operations: Context-dependent, not always needed for trusted files
    // - JSON operations: json_last_error() not needed for trusted input
    // These are better enforced through code review for specific contexts.

    /**
     * Check for N+1 query patterns
     *
     * Only flags obvious cases to reduce false positives.
     * More nuanced detection is handled by code review.
     *
     * @param string $filePath File path for error reporting
     * @param string $content File content
     * @return void
     */
    private function checkNPlusOneQueries(string $filePath, string $content): void
    {
        // Only check for the most obvious pattern: query() call inside foreach block
        // This pattern: foreach (...) { ... $db->query( ... }
        // Use a more specific regex that looks for query inside a foreach block
        if (preg_match('/foreach\s*\([^)]+\)\s*\{[^}]*\$(?:db|this->_db)\s*->\s*query\s*\(/s', $content)) {
            $this->warnings[] = "$filePath: Potential N+1 query pattern - database query inside foreach loop. Consider using JOINs or batch operations.";
        }

        // NOTE: Removed these overly aggressive checks:
        // - "High number of queries" - Many legitimate uses for multiple queries
        // - "Individual record lookups" - Too many false positives
    }

    // NOTE: checkCachingOpportunities() removed - too contextual.
    // Caching decisions require understanding the full context:
    // - How often is this code called?
    // - What's the data volatility?
    // - What's the infrastructure?
    // This is better handled in code review.

    // NOTE: checkPHPDocCompleteness() removed - redundant with type declarations.
    //
    // With PHP 8+ typed parameters and return types enforced, @param/@return
    // annotations are largely redundant. The checkPHPDocBlocks() method already
    // ensures public methods have a docblock for the description.
    //
    // Benefits of removal:
    // - Reduces noise from "@param missing" when types are already declared
    // - Types are enforced at runtime; docblocks are just for IDEs
    // - Allows simpler docblocks: just description + @throws when needed

    /**
     * Check regression test structure and issue linking
     *
     * @param string $filePath File path for error reporting
     * @param string $content File content
     * @param array $lines File lines
     * @return void
     */
    private function checkRegressionTestStructure(string $filePath, string $content, array $lines): void
    {
        // Only check files in tests/regression/ directory
        if (strpos($filePath, 'tests/regression/') === false) {
            return;
        }

        // Skip template files
        if (strpos($filePath, 'Template.php') !== false || strpos($filePath, 'README.md') !== false) {
            return;
        }

        $filename = basename($filePath, '.php');

        // Check filename pattern: Issue{Number}RegressionTest
        if (!preg_match('/^Issue(\d+)RegressionTest$/', $filename, $matches)) {
            $this->errors[] = "$filePath: Regression test filename must follow pattern Issue{Number}RegressionTest.php";
            return;
        }

        $issueNumber = $matches[1];

        // Check for required annotations
        if (!preg_match('/@issue\s+' . preg_quote($issueNumber, '/') . '\b/', $content)) {
            $this->errors[] = "$filePath: Missing @issue annotation. Add: @issue $issueNumber";
        }

        if (!preg_match('/@link\s+.*github\.com.*issues\/' . preg_quote($issueNumber, '/') . '\b/', $content)) {
            $this->errors[] = "$filePath: Missing @link annotation. Add: @link https://github.com/unibrain1/elanregistry/issues/$issueNumber";
        }

        // Check for class name consistency
        if (!preg_match('/class\s+Issue' . preg_quote($issueNumber, '/') . 'RegressionTest/', $content)) {
            $this->errors[] = "$filePath: Class name must match filename: Issue{$issueNumber}RegressionTest";
        }

        // Check for test method naming pattern (warning only)
        if (!preg_match('/testIssue' . preg_quote($issueNumber, '/') . '_/', $content)) {
            $this->warnings[] = "$filePath: Consider using test method pattern: testIssue{$issueNumber}_SpecificBehavior()";
        }

        // Check for issue description in PHPDoc
        if (!preg_match('/\*\s*(Description|GitHub Issue):\s*.*\b' . preg_quote($issueNumber, '/') . '\b/', $content)) {
            $this->warnings[] = "$filePath: Consider adding issue description in PHPDoc comment";
        }

        // Check that test extends TestCase
        if (!preg_match('/extends\s+TestCase/', $content)) {
            $this->errors[] = "$filePath: Regression test must extend PHPUnit\\Framework\\TestCase";
        }

        // Check for proper namespace/use statements
        if (!preg_match('/use\s+PHPUnit\\\Framework\\\TestCase/', $content)) {
            $this->warnings[] = "$filePath: Consider using explicit PHPUnit\\Framework\\TestCase import";
        }
    }

    /**
     * Strip <script> blocks from content to avoid false positives on JavaScript
     *
     * @param string $content File content
     * @return string Content with script blocks removed
     */
    private function stripScriptBlocks(string $content): string
    {
        return preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content) ?? $content;
    }

    /**
     * Print results summary
     *
     * @param bool $isStaged Whether this is for staged files
     * @return void
     */
    private function printResults(bool $isStaged = false): void
    {
        echo "\n\n";
        echo "=" . str_repeat("=", 60) . "\n";
        echo "📊 CODING STANDARDS CHECK RESULTS\n";
        echo "=" . str_repeat("=", 60) . "\n";
        echo "Files checked: $this->filesChecked\n";
        echo "Errors: " . count($this->errors) . "\n";
        echo "Warnings: " . count($this->warnings) . "\n";

        if ($this->strictMode && count($this->warnings) > 0) {
            echo "Mode: STRICT (warnings treated as blocking)\n";
        }

        echo "\n";

        // Always show errors in detail
        if (count($this->errors) > 0) {
            echo "❌ BLOCKING ISSUES (must fix):\n";
            echo "-" . str_repeat("-", 58) . "\n";
            foreach ($this->errors as $error) {
                echo "• $error\n";
            }
            echo "\n";
        }

        // Show warnings based on mode
        if (count($this->warnings) > 0) {
            if ($this->verboseMode || $this->strictMode) {
                // Verbose/Strict mode: show all warnings
                echo "⚠️  WARNINGS:\n";
                echo "-" . str_repeat("-", 58) . "\n";
                foreach ($this->warnings as $warning) {
                    echo "• $warning\n";
                }
                echo "\n";
            } else {
                // Normal mode: show summary only
                echo "⚠️  " . count($this->warnings) . " advisory warning(s) found\n";
                echo "   Run with --verbose to see details\n\n";
            }
        }

        // Final status
        if ($this->filesChecked === 0) {
            // Handled by run()'s zero-files guard, which fails the run — don't
            // print a misleadingly reassuring "passed" message here too.
        } elseif (count($this->errors) === 0 && count($this->warnings) === 0) {
            echo "✅ All coding standards checks passed!\n";
            echo "🚀 Ready for PR creation!\n\n";
        } elseif (count($this->errors) === 0) {
            echo "✅ No blocking issues found.\n";
            if ($this->strictMode) {
                echo "⚠️  Warnings are blocking in strict mode.\n";
            }
            echo "\n";
        } else {
            echo "💡 Fix the blocking issues above before committing.\n";
            echo "📖 See docs/development/CODING_STANDARDS.md for details.\n\n";
        }

        // Only show quick fixes if there are errors
        if (count($this->errors) > 0) {
            echo "🔧 Quick fixes:\n";
            echo "• Add declare(strict_types=1); after <?php in new files\n";
            echo "• Add return types: function name(): returnType\n";
            echo "• Add PHPDoc blocks to public methods\n";
            echo "• Use Token::check() for CSRF protection\n";
            echo "• Use prepared statements: \$db->query(\$sql, [\$params])\n";
            echo "• Use specific exception types instead of generic Exception\n";
            echo "• For regression tests: Add @issue and @link annotations\n\n";
        }
    }
}

// Run the checker
try {
    $checker = new CodingStandardsChecker();
    exit($checker->run($argv));
} catch (RuntimeException $e) {
    fwrite(STDERR, "❌ " . $e->getMessage() . "\n");
    exit(2);
}