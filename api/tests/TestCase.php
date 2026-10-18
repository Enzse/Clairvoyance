<?php

namespace Tests;

class AssertionFailed extends \Exception
{
}

/**
 * Base class for tests run by tests/run.php. Public methods named test* are run in order,
 * each on a fresh instance. An optional static setUpBeforeClass() runs once per class.
 */
abstract class TestCase
{
    protected function setUp(): void
    {
    }

    public function runTest(string $method): void
    {
        $this->setUp();
        $this->$method();
    }

    protected function assertTrue($condition, string $message = ''): void
    {
        if ($condition !== true) {
            $this->fail($message ?: 'Expected true, got ' . $this->export($condition));
        }
    }

    protected function assertSame($expected, $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $this->fail(($message ? "{$message}: " : '') . 'expected ' . $this->export($expected) . ', got ' . $this->export($actual));
        }
    }

    protected function assertNull($actual, string $message = ''): void
    {
        $this->assertSame(null, $actual, $message);
    }

    /** Float comparison, rounded to $decimals places. */
    protected function assertNear(float $expected, ?float $actual, int $decimals = 4, string $message = ''): void
    {
        if ($actual === null || round($expected, $decimals) !== round($actual, $decimals)) {
            $this->fail(($message ? "{$message}: " : '') . "expected ~{$expected}, got " . $this->export($actual));
        }
    }

    protected function assertThrows(callable $fn, string $class, string $messageContains = ''): \Throwable
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            if (!$e instanceof $class) {
                $this->fail("Expected {$class}, got " . get_class($e) . ': ' . $e->getMessage());
            }
            if ($messageContains !== '' && stripos($e->getMessage(), $messageContains) === false) {
                $this->fail("Expected message containing '{$messageContains}', got '{$e->getMessage()}'");
            }
            return $e;
        }
        $this->fail("Expected {$class} to be thrown");
    }

    protected function fail(string $message): void
    {
        throw new AssertionFailed($message);
    }

    private function export($value): string
    {
        return var_export($value, true);
    }
}
