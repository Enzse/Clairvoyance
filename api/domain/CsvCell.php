<?php

namespace Domain;

/**
 * Strict parsing of CSV cells. Anything ambiguous or impossible returns null so the import
 * can report it, instead of guessing (the old importer turned 25/12/2025 into 2027).
 */
final class CsvCell
{
    public const DATE_FORMATS = ['YYYY-MM-DD' => '!Y-m-d', 'DD/MM/YYYY' => '!d/m/Y', 'MM/DD/YYYY' => '!m/d/Y', 'YYYY-MM' => '!Y-m'];

    /** Y-m-d, or null if the text isn't a real date in that format (31/02/2025 is rejected). */
    public static function date(string $text, string $format): ?string
    {
        $pattern = self::DATE_FORMATS[$format] ?? throw new \InvalidArgumentException("Unknown date format: {$format}");
        $date = \DateTimeImmutable::createFromFormat($pattern, trim($text));
        $problems = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($problems && ($problems['warning_count'] || $problems['error_count']))) {
            return null;
        }
        return $date->format('Y-m-d');
    }

    /** Accepts 1234.5, -12, .5, 1,234.5 and 95.3% (stored as 95.3); anything else is null. */
    public static function number(string $text): ?float
    {
        $text = str_replace(' ', '', $text);
        if (substr($text, -1) === '%') {
            $text = substr($text, 0, -1);
        }
        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $text)) {
            $text = str_replace(',', '', $text);
        }
        if (!preg_match('/^-?(\d+\.?\d*|\.\d+)$/', $text)) {
            return null;
        }
        $value = (float)$text;
        return abs($value) < 1e13 ? $value : null;
    }
}
