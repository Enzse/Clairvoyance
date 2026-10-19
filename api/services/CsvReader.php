<?php

namespace Services;

use Core\HttpException;

final class CsvReader
{
    /**
     * Comma-separated, first row is the header. Blank lines are skipped.
     * @return array{0: string[], 1: array<int, string[]>} headers, and rows keyed by line number
     */
    public static function read(string $path, int $maxRows): array
    {
        $handle = fopen($path, 'r');
        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if (!$headers || $headers === [null]) {
                throw HttpException::badRequest('The file is empty');
            }
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]); // UTF-8 byte order mark
            $headers = array_map('trim', $headers);
            if (count(array_unique($headers)) !== count($headers)) {
                throw HttpException::badRequest('Column names in the first row must be unique');
            }

            $rows = [];
            $line = 1;
            while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if ($cells === [null] || implode('', array_map('trim', $cells)) === '') {
                    continue;
                }
                if (count($rows) >= $maxRows) {
                    throw HttpException::badRequest("At most {$maxRows} rows per file");
                }
                $rows[$line] = $cells;
            }
            return [$headers, $rows];
        } finally {
            fclose($handle);
        }
    }
}
