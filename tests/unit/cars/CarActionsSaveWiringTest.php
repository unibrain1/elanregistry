<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for the car save endpoint's action-routing fallback.
 *
 * Covers:
 * - app/api/cars/save.php (the switch statement's default: branch)
 *
 * The endpoint file cannot be require()'d from PHPUnit: every response path
 * ends in ApiResponse::send(), which calls exit and would terminate the test
 * runner. The action router dispatches on Input::get('action'), which only
 * exists during a real HTTP request, so the unroutable-action fallback is
 * asserted against the endpoint file's source text.
 *
 * Deliberately narrow: this is not a wiring audit of save.php's auth, CSRF and
 * method guards. The database-backed behaviour of addCar / updateCar is
 * exercised for real against the Car class in
 * tests/integration/database/CarDatabaseOperationsTest.php, and removeImages'
 * underlying CarImageProcessor::removeImage() in
 * tests/integration/CarImageLifecycleTest.php. fetchImages (Car::exists() /
 * Car::images()) has no real coverage yet — a pre-existing gap, not
 * introduced here.
 *
 * Also out of scope: per-action ApiResponse format-consistency across all of
 * save.php's branches (the sibling wiring test asserts this for history.php /
 * chassis-validate.php). save.php can't share that loop unchanged: its
 * substr_count('->send()') would count two occurrences sitting inside
 * comments (lines 150, 239), and it legitimately calls json_encode() 9 times
 * — 6 to serialize $errors into log messages, 3 to build the image column —
 * which the invariant doesn't account for. Stripping comments via
 * token_get_all() before counting would make the factory/send half of the
 * invariant hold (33 === 33); left as a follow-up if it proves needed.
 * Generic Pattern A/B format compliance is covered by
 * tests/unit/api/ApiResponseTest.php.
 *
 * @author Elan Registry Development Team
 */
#[Group('fast')]
#[Group('unit')]
#[Group('car-actions')]
final class CarActionsSaveWiringTest extends TestCase
{
    /** Endpoint path, relative to the repository root. */
    private const SAVE_ENDPOINT = 'app/api/cars/save.php';

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Read an endpoint's source text for wiring assertions.
     *
     * @param string $relativePath Path relative to the repository root
     * @return string The endpoint file contents
     */
    private function readEndpointSource(string $relativePath): string
    {
        $filePath = __DIR__ . '/../../../' . $relativePath;
        $this->assertFileExists($filePath, "Endpoint file must exist: {$relativePath}");

        $content = file_get_contents($filePath);
        $this->assertIsString($content, "Endpoint file must be readable: {$relativePath}");

        return $content;
    }

    // =========================================================================
    // save.php — action routing (source inspection)
    // =========================================================================

    /**
     * save.php answers an action it cannot route with a 400 rather than falling
     * through silently.
     *
     * Source inspection: reaching the branch needs a real request carrying an
     * unrecognised `action` parameter, and the response ends in send()/exit.
     * `default:` is part of the asserted sequence on purpose — a bare check for
     * the error string would still pass if the fallback were moved into a
     * routable case, leaving an unknown action to drop out of the switch with
     * no response at all.
     */
    public function testInvalidActionReturnsNoValidActionError(): void
    {
        $content = $this->readEndpointSource(self::SAVE_ENDPOINT);

        $this->assertStringContainsString(
            'switch ($action) {',
            $content,
            'Endpoint must route on the action parameter with a switch statement'
        );
        $this->assertMatchesRegularExpression(
            '/\bdefault:\s*ApiResponse::error\(\'No valid action\', 400\)/',
            $content,
            'An unroutable action must fall to default: and return HTTP 400 via ApiResponse::error()'
        );
        $this->assertMatchesRegularExpression(
            '/ApiResponse::error\(\'No valid action\', 400\)\s*->withLogging\([^;]*LOG_CATEGORY_VALIDATION_ERROR/s',
            $content,
            'The unroutable-action response must be logged under the validation-error category'
        );
        $this->assertMatchesRegularExpression(
            '/ApiResponse::error\(\'No valid action\', 400\)[^;]*->send\(\);/s',
            $content,
            'The unroutable-action response must actually be emitted via ->send()'
        );
    }
}
