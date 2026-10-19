<?php

namespace Tests\Unit;

use Domain\Status;
use Tests\TestCase;

class StatusTest extends TestCase
{
    public function testHigherIsBetter(): void
    {
        $this->assertSame('green', Status::evaluate('higher_is_better', 100, 100, 10));
        $this->assertSame('amber', Status::evaluate('higher_is_better', 90, 100, 10));
        $this->assertSame('red', Status::evaluate('higher_is_better', 89.99, 100, 10));
    }

    public function testLowerIsBetter(): void
    {
        $this->assertSame('green', Status::evaluate('lower_is_better', 100, 100, 10));
        $this->assertSame('amber', Status::evaluate('lower_is_better', 110, 100, 10));
        $this->assertSame('red', Status::evaluate('lower_is_better', 110.01, 100, 10));
    }

    public function testZeroAndNegativeTargets(): void
    {
        $this->assertSame('red', Status::evaluate('higher_is_better', -1, 0, 10)); // no band around zero
        $this->assertSame('green', Status::evaluate('higher_is_better', 0, 0, 10));
        // Target -100 (e.g. a loss limit): 10% band reaches -110
        $this->assertSame('amber', Status::evaluate('higher_is_better', -105, -100, 10));
        $this->assertSame('red', Status::evaluate('higher_is_better', -111, -100, 10));
    }

    public function testNoStatusWithoutDirectionValueOrTarget(): void
    {
        $this->assertNull(Status::evaluate('none', 5, 10, 10));
        $this->assertNull(Status::evaluate('higher_is_better', null, 10, 10));
        $this->assertNull(Status::evaluate('higher_is_better', 5, null, 10));
    }

    public function testFloatNoiseDoesNotFlipBoundary(): void
    {
        $this->assertSame('green', Status::evaluate('higher_is_better', 0.1 + 0.2, 0.3, 0));
    }
}
