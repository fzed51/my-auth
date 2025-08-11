<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @covers \PHPUnit\Framework\TestCase
 */
final class BasicTest extends TestCase
{
    public function testPhpUnitIsWorking(): void
    {
        $this->assertTrue(true);
    }
    
    public function testBasicMath(): void
    {
        $this->assertEquals(4, 2 + 2);
        $this->assertNotEquals(5, 2 + 2);
    }
}
