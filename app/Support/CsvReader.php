<?php

namespace App\Support;

use RuntimeException;

/**
 * Reads the CSV an accountant saves from Excel: UTF-8 with or without BOM, or Windows-1256 (Arabic Excel),
 * delimiter `,` `;` or tab, quoted fields (also across lines), blank lines skipped. No package.
 * The first row is the header; rows come back keyed by the header text, each with its line number.
 */
class CsvReader
{
    public const MAX_ROWS = 5000;

    /**
     * @return array{headers: list<string>, rows: list<array{line: int, cells: array<string, string>}>}
     *
     * @throws RuntimeException with an Arabic message the user can act on
     */
    public static function read(string $contents): array
    {
        $contents = self::toUtf8($contents);

        if (trim($contents) === '') {
            throw new RuntimeException('الملف فاضي.');
        }

        $delimiter = self::delimiter($contents);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        $headers = null;
        $rows = [];
        $line = 0;

        while (($record = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            $line++;
            $record = array_map(fn (?string $cell) => trim((string) $cell), $record);

            if ($record === [''] || implode('', $record) === '') {
                continue;
            }

            if ($headers === null) {
                $headers = $record;

                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                fclose($stream);

                throw new RuntimeException('الملف أكبر من '.self::MAX_ROWS.' سطر. قسّمه لأكتر من ملف.');
            }

            $cells = [];

            foreach ($headers as $index => $header) {
                $cells[$header] = $record[$index] ?? '';
            }

            $rows[] = ['line' => $line, 'cells' => $cells];
        }

        fclose($stream);

        if ($headers === null || $rows === []) {
            throw new RuntimeException('ما لقيت بالملف سطور بيانات تحت سطر العناوين.');
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * UTF-8 as it is (minus a BOM); anything else is taken as Windows-1256, the Arabic Excel default.
     */
    private static function toUtf8(string $contents): string
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        if (mb_check_encoding($contents, 'UTF-8')) {
            return $contents;
        }

        // mbstring has no Windows-1256; iconv does.
        $converted = function_exists('iconv') ? iconv('CP1256', 'UTF-8//IGNORE', $contents) : false;

        if ($converted === false) {
            throw new RuntimeException('ما قدرت أقرأ ترميز الملف. احفظه من Excel بصيغة «CSV UTF-8».');
        }

        return $converted;
    }

    /**
     * The delimiter used most on the header line: Arabic-locale Excel writes `;`, others `,` or tab.
     */
    private static function delimiter(string $contents): string
    {
        $firstLine = strtok($contents, "\r\n") ?: '';
        $counts = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }
}
