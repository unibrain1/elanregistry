<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CarTest extends TestCase
{
    public function testFind(): void
    {
        $this->assertInstanceOf(
            Car::class,
            Car::find(1)
        );
    }
}
