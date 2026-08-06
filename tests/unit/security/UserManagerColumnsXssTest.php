<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use PHPUnit\Framework\Attributes\Group;

/**
 * Test user_manager_columns.php for stored XSS (#1499)
 *
 * Verifies that the admin User Manager column renderer escapes
 * user-controlled values (email, username, permission names) before
 * returning them for display.
 */
#[Group('security')]
#[Group('user-manager-columns')]
class UserManagerColumnsXssTest extends TestCase
{
    /**
     * Load user_manager_columns.php and return the
     * $user_manager_column_data closure it defines.
     *
     * @param int $uCount Value for $uCount in the closure's captured scope
     * @param int $maxUsers Value for $maxUsers in the closure's captured scope
     * @param int $act Value for $act in the closure's captured scope
     * @return callable(object, string): (string|null)
     */
    private function loadColumnDataClosure(int $uCount = 0, int $maxUsers = 100, int $act = 0): callable
    {
        $file = dirname(__DIR__, 3) . '/usersc/includes/user_manager_columns.php';

        $this->assertFileExists($file, 'user_manager_columns.php is missing — XSS escaping cannot be verified');

        // require, not require_once — each test needs the closure redefined in its own scope.
        require $file;

        /** @var callable(object, string): (string|null) $user_manager_column_data */
        return $user_manager_column_data;
    }

    public function testEmailColumnIsEscaped(): void
    {
        $columnData = $this->loadColumnDataClosure();

        $user = (object) ['email' => '<script>alert(1)</script>'];
        $result = $columnData($user, 'email');

        $this->assertSame(
            htmlspecialchars('<script>alert(1)</script>', ENT_QUOTES, 'UTF-8'),
            $result
        );
        $this->assertStringNotContainsString('<script>', (string) $result);
    }

    public function testUsernameColumnIsEscaped(): void
    {
        $columnData = $this->loadColumnDataClosure();

        $user = (object) ['username' => '<script>alert(1)</script>'];
        $result = $columnData($user, 'username');

        $this->assertSame(
            htmlspecialchars('<script>alert(1)</script>', ENT_QUOTES, 'UTF-8'),
            $result
        );
        $this->assertStringNotContainsString('<script>', (string) $result);
    }

    public function testPermsColumnIsEscaped(): void
    {
        $columnData = $this->loadColumnDataClosure(uCount: 0, maxUsers: 100);

        $user = (object) ['perms' => '<img src=x onerror=alert(1)>'];
        $result = $columnData($user, 'perms');

        $this->assertSame(
            htmlspecialchars('<img src=x onerror=alert(1)>', ENT_QUOTES, 'UTF-8'),
            $result
        );
        $this->assertStringNotContainsString('<img', (string) $result);
    }

    public function testPermsColumnHandlesNullWithoutError(): void
    {
        $columnData = $this->loadColumnDataClosure(uCount: 0, maxUsers: 100);

        $user = (object) ['perms' => null];
        $result = $columnData($user, 'perms');

        $this->assertSame('', $result);
    }

    public function testPermsColumnReturnsNullWhenUserCountExceedsMax(): void
    {
        $columnData = $this->loadColumnDataClosure(uCount: 500, maxUsers: 100);

        $user = (object) ['perms' => 'Admin'];
        $result = $columnData($user, 'perms');

        $this->assertNull($result);
    }

    public function testDefaultColumnHandlesNullWithoutError(): void
    {
        $columnData = $this->loadColumnDataClosure();

        $user = (object) ['email' => null];
        $result = $columnData($user, 'email');

        $this->assertSame('', $result);
    }

    public function testDefaultColumnHandlesNonStringValue(): void
    {
        $columnData = $this->loadColumnDataClosure();

        // A custom numeric column (e.g. "phone") added per the file's own
        // CUSTOMIZATION EXAMPLES docblock would reach this branch with a non-string value.
        $user = (object) ['phone' => 5551234];
        $result = $columnData($user, 'phone');

        $this->assertSame('5551234', $result);
    }
}
