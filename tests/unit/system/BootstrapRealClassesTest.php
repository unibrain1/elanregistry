<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Guards the unit bootstrap's "load the real class, don't mock it" rule (#1554).
 *
 * Config, Session, Token and Input are dependency-free upstream UserSpice classes,
 * so tests/bootstrap-unit.php require_once's the real files instead of declaring
 * fakes. That distinction is invisible at the call site: the same file already
 * declares a mock DB, so if one of these classes ever gained a DB:: dependency and
 * someone "fixed" the resulting failure by re-adding a fake class, the CSRF and
 * input-sanitization tests would keep passing while proving nothing.
 *
 * These assertions fail loudly the moment a redefinition shadows the real file.
 */
#[Group('system')]
#[Group('bootstrap')]
class BootstrapRealClassesTest extends TestCase
{
    /**
     * Each class must resolve to its upstream file in users/classes/.
     *
     * @param string $class Bare (non-namespaced) UserSpice class name
     */
    #[DataProvider('realUpstreamClassProvider')]
    public function testUnitBootstrapLoadsRealUpstreamClassesNotRedefinitions(string $class): void
    {
        $this->assertTrue(class_exists($class), "{$class} must be available to unit tests");

        $file = (new ReflectionClass($class))->getFileName();

        $this->assertStringEndsWith(
            "/users/classes/{$class}.php",
            (string) $file,
            "Unit test bootstrap must load the real upstream {$class} class, not a mock redefinition"
        );
    }

    /** @return array<string, array{string}> */
    public static function realUpstreamClassProvider(): array
    {
        return [
            'Config'  => ['Config'],
            'Session' => ['Session'],
            'Token'   => ['Token'],
            'Input'   => ['Input'],
        ];
    }
}
