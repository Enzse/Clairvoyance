<?php

namespace Tests\Unit;

use Domain\CsvCell;
use Tests\TestCase;

class CsvCellTest extends TestCase
{
    public function testDatesInEachFormat(): void
    {
        $this->assertSame('2025-12-25', CsvCell::date('2025-12-25', 'YYYY-MM-DD'));
        $this->assertSame('2025-12-25', CsvCell::date('25/12/2025', 'DD/MM/YYYY'));
        $this->assertSame('2025-12-25', CsvCell::date('12/25/2025', 'MM/DD/YYYY'));
        $this->assertSame('2025-06-01', CsvCell::date('2025-06', 'YYYY-MM'));
        $this->assertSame('2025-06-05', CsvCell::date(' 5/6/2025 ', 'DD/MM/YYYY')); // no leading zeros, padded
    }

    public function testImpossibleOrMismatchedDatesAreRejected(): void
    {
        $this->assertNull(CsvCell::date('31/02/2025', 'DD/MM/YYYY'));  // would roll over to March
        $this->assertNull(CsvCell::date('25/12/2025', 'MM/DD/YYYY'));  // month 25 (the old importer made this 2027)
        $this->assertNull(CsvCell::date('2025-12-25', 'DD/MM/YYYY'));
        $this->assertNull(CsvCell::date('2025-12-25 10:00', 'YYYY-MM-DD')); // trailing data
        $this->assertNull(CsvCell::date('January', 'YYYY-MM-DD'));
        $this->assertNull(CsvCell::date('', 'YYYY-MM-DD'));
    }

    public function testNumbers(): void
    {
        $this->assertSame(1234.5, CsvCell::number('1234.5'));
        $this->assertSame(-12.0, CsvCell::number('-12'));
        $this->assertSame(0.5, CsvCell::number('.5'));
        $this->assertSame(1234567.25, CsvCell::number('1,234,567.25'));
        $this->assertSame(95.3, CsvCell::number('95.3%'));
        $this->assertSame(1250.0, CsvCell::number('1 250'));
    }

    public function testAmbiguousOrInvalidNumbersAreRejected(): void
    {
        $this->assertNull(CsvCell::number('1,25'));      // decimal comma or thousands? refuse to guess
        $this->assertNull(CsvCell::number('12,34,567'));  // malformed grouping
        $this->assertNull(CsvCell::number('$100'));
        $this->assertNull(CsvCell::number('lots'));
        $this->assertNull(CsvCell::number('1e5'));
        $this->assertNull(CsvCell::number('-'));
        $this->assertNull(CsvCell::number('100000000000000')); // beyond storage range
    }
}
