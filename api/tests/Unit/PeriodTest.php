<?php

namespace Tests\Unit;

use Domain\Period;
use Tests\TestCase;

class PeriodTest extends TestCase
{
    public function testAlign(): void
    {
        $this->assertSame('2025-06-15', Period::align('2025-06-15', 'daily'));
        $this->assertSame('2025-06-09', Period::align('2025-06-15', 'weekly')); // Sunday -> its Monday
        $this->assertSame('2025-06-09', Period::align('2025-06-09', 'weekly'));
        $this->assertSame('2024-12-30', Period::align('2025-01-05', 'weekly')); // across a year boundary
        $this->assertSame('2025-06-01', Period::align('2025-06-30', 'monthly'));
        $this->assertSame('2025-07-01', Period::align('2025-08-15', 'quarterly'));
        $this->assertSame('2025-10-01', Period::align('2025-12-31', 'quarterly'));
        $this->assertSame('2025-01-01', Period::align('2025-12-31', 'yearly'));
    }

    public function testShift(): void
    {
        $this->assertSame('2025-02-01', Period::next('2025-01-01', 'monthly'));
        $this->assertSame('2026-01-01', Period::next('2025-10-01', 'quarterly'));
        $this->assertSame('2024-10-01', Period::previous('2025-01-01', 'quarterly'));
        $this->assertSame('2025-01-06', Period::next('2024-12-30', 'weekly'));
        $this->assertSame('2024-01-01', Period::shift('2025-01-01', 'monthly', -12));
    }

    public function testPeriodsWithin(): void
    {
        $this->assertSame(['2025-04-01', '2025-05-01', '2025-06-01'], Period::periodsWithin('2025-04-01', 'quarterly', 'monthly'));
        $this->assertSame(['2025-06-01'], Period::periodsWithin('2025-06-01', 'monthly', 'monthly'));
        // Weeks belong to the month their Monday falls in
        $this->assertSame(
            ['2025-06-02', '2025-06-09', '2025-06-16', '2025-06-23', '2025-06-30'],
            Period::periodsWithin('2025-06-01', 'monthly', 'weekly')
        );
        $this->assertSame(30, count(Period::periodsWithin('2025-06-01', 'monthly', 'daily')));
    }

    public function testCannotViewFinerThanFrequency(): void
    {
        $this->assertThrows(fn() => Period::periodsWithin('2025-06-01', 'weekly', 'monthly'), \InvalidArgumentException::class);
        $this->assertTrue(Period::isAtLeast('monthly', 'weekly'));
        $this->assertTrue(Period::isAtLeast('monthly', 'monthly'));
        $this->assertSame(false, Period::isAtLeast('weekly', 'monthly'));
        $this->assertSame('quarterly', Period::coarser('monthly', 'quarterly'));
    }

    public function testLabels(): void
    {
        $this->assertSame('Jun 2025', Period::label('2025-06-01', 'monthly'));
        $this->assertSame('Q3 2025', Period::label('2025-07-01', 'quarterly'));
        $this->assertSame('W27 2025', Period::label('2025-06-30', 'weekly'));
        $this->assertSame('W01 2025', Period::label('2024-12-30', 'weekly')); // ISO week-year
        $this->assertSame('2025', Period::label('2025-01-01', 'yearly'));
    }

    public function testStrictParsing(): void
    {
        $this->assertThrows(fn() => Period::parse('2025-02-30'), \InvalidArgumentException::class);
        $this->assertThrows(fn() => Period::parse('2025-6-1'), \InvalidArgumentException::class);
        $this->assertThrows(fn() => Period::parse('June 2025'), \InvalidArgumentException::class);
        $this->assertTrue(Period::isValidDate('2024-02-29'));
    }
}
