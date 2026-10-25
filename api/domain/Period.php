<?php

namespace Domain;

use DateTimeImmutable;

/**
 * Calendar periods, identified by their start date (Y-m-d). Weeks are ISO weeks starting
 * Monday. A coarser grain contains the finer periods that *start* inside it, so a week
 * belongs to the month its Monday falls in.
 */
final class Period
{
    public const FREQUENCIES = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'];

    public static function isValidFrequency(string $frequency): bool
    {
        return in_array($frequency, self::FREQUENCIES, true);
    }

    /** True when $grain is the same as or coarser than $frequency. */
    public static function isAtLeast(string $grain, string $frequency): bool
    {
        return array_search($grain, self::FREQUENCIES, true) >= array_search($frequency, self::FREQUENCIES, true);
    }

    public static function coarser(string $a, string $b): string
    {
        return self::isAtLeast($a, $b) ? $a : $b;
    }

    /** Start of the period containing $date. */
    public static function align(string $date, string $frequency): string
    {
        $d = self::parse($date);
        switch ($frequency) {
            case 'daily':
                return $d->format('Y-m-d');
            case 'weekly':
                return $d->modify('-' . ((int)$d->format('N') - 1) . ' days')->format('Y-m-d');
            case 'monthly':
                return $d->format('Y-m-01');
            case 'quarterly':
                $month = intdiv((int)$d->format('n') - 1, 3) * 3 + 1;
                return sprintf('%s-%02d-01', $d->format('Y'), $month);
            case 'yearly':
                return $d->format('Y-01-01');
        }
        throw new \InvalidArgumentException("Unknown frequency: {$frequency}");
    }

    public static function isAligned(string $date, string $frequency): bool
    {
        return self::align($date, $frequency) === $date;
    }

    /** Start of the period after the one starting at $start. */
    public static function next(string $start, string $frequency): string
    {
        return self::shift($start, $frequency, 1);
    }

    public static function previous(string $start, string $frequency): string
    {
        return self::shift($start, $frequency, -1);
    }

    public static function shift(string $start, string $frequency, int $count): string
    {
        $steps = [
            'daily' => [1, 'day'],
            'weekly' => [7, 'day'],
            'monthly' => [1, 'month'],
            'quarterly' => [3, 'month'],
            'yearly' => [1, 'year'],
        ];
        [$size, $unit] = $steps[$frequency] ?? throw new \InvalidArgumentException("Unknown frequency: {$frequency}");
        $n = $size * $count;
        return self::parse($start)->modify(($n >= 0 ? '+' : '') . "{$n} {$unit}")->format('Y-m-d');
    }

    /**
     * Starts of the $frequency periods inside the $grain period starting at $grainStart.
     * @return string[]
     */
    public static function periodsWithin(string $grainStart, string $grain, string $frequency): array
    {
        if (!self::isAtLeast($grain, $frequency)) {
            throw new \InvalidArgumentException("A {$frequency} KPI cannot be viewed {$grain}");
        }
        $end = self::next($grainStart, $grain);
        // The first period starting on or after the grain start (relevant for weeks)
        $current = self::align($grainStart, $frequency);
        if ($current < $grainStart) {
            $current = self::next($current, $frequency);
        }
        $periods = [];
        for (; $current < $end; $current = self::next($current, $frequency)) {
            $periods[] = $current;
        }
        return $periods;
    }

    /**
     * The periods inside a viewed period that have started by $today. Days and weeks still to
     * come aren't missing yet, so they aren't counted as expected.
     * @return string[]
     */
    public static function elapsedWithin(string $grainStart, string $grain, string $frequency, string $today): array
    {
        return array_values(array_filter(self::periodsWithin($grainStart, $grain, $frequency), fn($p) => $p <= $today));
    }

    /**
     * The periods a KPI is reported for in a month, as shown on the data-entry form: each week
     * or day that starts in the month (up to $today), otherwise the one period containing it.
     * @return string[]
     */
    public static function inMonth(string $monthStart, string $frequency, string $today): array
    {
        if ($frequency !== 'daily' && $frequency !== 'weekly') {
            return [self::align($monthStart, $frequency)];
        }
        return array_values(array_filter(self::periodsWithin($monthStart, 'monthly', $frequency), fn($p) => $p <= $today));
    }

    /** Human label, e.g. "Jun 2025", "Q2 2025", "W23 2025". */
    public static function label(string $start, string $frequency): string
    {
        $d = self::parse($start);
        switch ($frequency) {
            case 'daily':
                return $d->format('j M Y');
            case 'weekly':
                return $d->format('\WW o');
            case 'monthly':
                return $d->format('M Y');
            case 'quarterly':
                return 'Q' . (intdiv((int)$d->format('n') - 1, 3) + 1) . ' ' . $d->format('Y');
            case 'yearly':
                return $d->format('Y');
        }
        throw new \InvalidArgumentException("Unknown frequency: {$frequency}");
    }

    /** Strict Y-m-d parse; rejects impossible dates like 2025-02-30. */
    public static function parse(string $date): DateTimeImmutable
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($d === false || $d->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException("Invalid date: {$date} (expected YYYY-MM-DD)");
        }
        return $d;
    }

    public static function isValidDate(string $date): bool
    {
        try {
            self::parse($date);
            return true;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }
}
