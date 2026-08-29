<?php

declare(strict_types=1);

use ElanRegistry\Exceptions\ElanRegistryException;
use ElanRegistry\Exceptions\OwnerCreationException;
use ElanRegistry\Exceptions\OwnerDatabaseException;
use ElanRegistry\Exceptions\OwnerException;
use ElanRegistry\Exceptions\OwnerSearchException;
use ElanRegistry\Exceptions\OwnerUpdateException;
use ElanRegistry\Exceptions\OwnerValidationException;
use PHPUnit\Framework\TestCase;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test cases for the OwnerException hierarchy
 *
 * Verifies that all owner-related exceptions properly extend OwnerException,
 * which in turn extends ElanRegistryException, maintaining backward
 * compatibility with existing catch blocks. Mirrors CarExceptionHierarchyTest.php.
 */
#[Group('unit')]
#[Group('exceptions')]
class OwnerExceptionHierarchyTest extends TestCase
{
    /**
     * All owner exception classes that should extend OwnerException
     */
    private const OWNER_EXCEPTION_CLASSES = [
        OwnerCreationException::class,
        OwnerSearchException::class,
        OwnerUpdateException::class,
        OwnerValidationException::class,
        OwnerDatabaseException::class,
    ];

    /**
     * Test that OwnerException is abstract and cannot be instantiated
     */
    public function testOwnerExceptionIsAbstract(): void
    {
        $reflection = new ReflectionClass(OwnerException::class);
        $this->assertTrue(
            $reflection->isAbstract(),
            'OwnerException must be abstract'
        );
    }

    /**
     * Test that OwnerException extends ElanRegistryException
     */
    public function testOwnerExceptionExtendsElanRegistryException(): void
    {
        $this->assertTrue(
            is_subclass_of(OwnerException::class, ElanRegistryException::class),
            'OwnerException should extend ElanRegistryException'
        );
    }

    /**
     * Test that all owner exceptions extend OwnerException
     *
     * @param string $className Exception class name to test
     */
    #[DataProvider('ownerExceptionClassProvider')]
    public function testOwnerExceptionExtendsOwnerException(string $className): void
    {
        $this->assertTrue(
            is_subclass_of($className, OwnerException::class),
            "{$className} should extend OwnerException"
        );
    }

    /**
     * Test backward compatibility - all owner exceptions are instanceof OwnerException
     *
     * @param string $className Exception class name to test
     */
    #[DataProvider('ownerExceptionClassProvider')]
    public function testAllOwnerExceptionsAreInstanceOfOwnerException(string $className): void
    {
        $exception = new $className();
        $this->assertInstanceOf(
            OwnerException::class,
            $exception,
            "{$className} should be instanceof OwnerException"
        );
    }

    /**
     * Test that OwnerException catch block catches all owner exceptions
     */
    public function testOwnerExceptionCatchBlockCatchesAllOwnerExceptions(): void
    {
        foreach (self::OWNER_EXCEPTION_CLASSES as $className) {
            $caught = false;
            try {
                throw new $className('Test');
            } catch (OwnerException $e) {
                $caught = true;
            }
            $this->assertTrue(
                $caught,
                "{$className} should be caught by catch (OwnerException)"
            );
        }
    }

    /**
     * Data provider for owner exception classes
     *
     * @return array<string, array<int, string>>
     */
    public static function ownerExceptionClassProvider(): array
    {
        $data = [];
        foreach (self::OWNER_EXCEPTION_CLASSES as $class) {
            $data[$class] = [$class];
        }
        return $data;
    }
}
