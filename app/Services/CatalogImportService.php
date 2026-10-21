<?php

namespace App\Services;

use App\Support\CsvReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Importing a catalogue (products, parties) from a CSV file, all or nothing.
 * `check()` reads and validates the whole file with the same rules as the forms and reports every problem
 * with its line; `import()` creates the rows in one transaction, so a file with one bad row imports nothing.
 * Rows are created through the models, so `company_id` always comes from the signed-in user.
 */
abstract class CatalogImportService
{
    /** Rows shown in the preview before the user confirms. */
    private const PREVIEW_ROWS = 10;

    /**
     * Accepted header texts per field, lower case (Arabic and English).
     *
     * @return array<string, list<string>>
     */
    abstract protected function columns(): array;

    /**
     * Fields that must have a column in the file.
     *
     * @return list<string>
     */
    abstract protected function requiredColumns(): array;

    /**
     * Turns the raw text of a row into what the rules expect (Arabic labels to keys, number formats).
     *
     * @param  array<string, ?string>  $row
     * @return array<string, ?string>
     */
    abstract protected function normalize(array $row): array;

    /**
     * @param  array<string, ?string>  $row
     * @return array<string, mixed>
     */
    abstract protected function rules(array $row): array;

    /**
     * @return array<string, string>
     */
    abstract protected function attributes(): array;

    /**
     * @return array<string, string>
     */
    abstract protected function messages(): array;

    /**
     * Problems that only show across rows (a barcode twice, a barcode already in the catalogue).
     *
     * @param  array<string, ?string>  $row
     * @param  array<string, mixed>  $state  kept between rows of one check
     * @return list<string>
     */
    protected function crossChecks(array $row, int $line, array &$state): array
    {
        return [];
    }

    /**
     * @param  array<string, ?string>  $row
     */
    abstract protected function create(array $row): void;

    /**
     * What the screens call the thing imported ("المواد").
     */
    abstract public function plural(): string;

    /**
     * Column help for the instructions: [header, required?, example].
     *
     * @return list<array{0: string, 1: bool, 2: string}>
     */
    abstract public function help(): array;

    /**
     * A sample file the user can fill in: UTF-8 with a BOM so Excel shows the Arabic right.
     */
    abstract public function sample(): string;

    /**
     * Reads and validates a whole file. Throws RuntimeException (Arabic message) when the file itself is unusable.
     *
     * @return array{rows: list<array<string, ?string>>, errors: list<array{line: int, message: string}>, count: int, preview: list<array<string, string>>}
     */
    public function check(string $contents): array
    {
        $file = CsvReader::read($contents);
        $fields = $this->mapHeaders($file['headers']);
        $state = [];
        $rows = [];
        $errors = [];

        foreach ($file['rows'] as ['line' => $line, 'cells' => $cells]) {
            $row = [];

            foreach ($fields as $header => $field) {
                $value = $cells[$header] ?? '';
                $row[$field] = $value === '' ? null : $value;
            }

            $row = $this->normalize($row);
            $messages = [];

            $validator = Validator::make($row, $this->rules($row), $this->messages(), $this->attributes());

            foreach ($validator->errors()->messages() as $fieldMessages) {
                $messages[] = $fieldMessages[0];
            }

            $messages = [...$messages, ...$this->crossChecks($row, $line, $state)];

            if ($messages === []) {
                $rows[] = $row;
            } else {
                $errors[] = ['line' => $line, 'message' => implode(' · ', $messages)];
            }
        }

        return ['rows' => $rows, 'errors' => $errors, 'count' => count($file['rows']), 'preview' => array_map($this->display(...), array_slice($rows, 0, self::PREVIEW_ROWS))];
    }

    /**
     * A row as the user reads it in the preview: Arabic column headers and readable values.
     *
     * @param  array<string, ?string>  $row
     * @return array<string, string>
     */
    private function display(array $row): array
    {
        $shown = [];

        foreach ($row as $field => $value) {
            $shown[$this->columns()[$field][1]] = $this->displayValue($field, $value);
        }

        return $shown;
    }

    /**
     * How one cell reads in the preview (a stored key becomes its Arabic label).
     */
    protected function displayValue(string $field, ?string $value): string
    {
        return $value ?? '—';
    }

    /**
     * Creates every row in one transaction. Returns how many were created.
     *
     * @param  list<array<string, ?string>>  $rows
     */
    public function import(array $rows): int
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $this->create($row);
            }
        });

        return count($rows);
    }

    /**
     * Header text of the file → field name. Unknown columns are ignored; a missing required one is an error.
     *
     * @param  list<string>  $headers
     * @return array<string, string>
     */
    private function mapHeaders(array $headers): array
    {
        $map = [];

        foreach ($headers as $header) {
            $key = mb_strtolower(trim($header));

            foreach ($this->columns() as $field => $aliases) {
                if (in_array($key, $aliases, true) && ! in_array($field, $map, true)) {
                    $map[$header] = $field;
                }
            }
        }

        foreach ($this->requiredColumns() as $field) {
            if (! in_array($field, $map, true)) {
                throw new RuntimeException('ما لقيت بسطر العناوين عمود «'.$this->columns()[$field][1].'». حمّل الملف النموذجي واتبع عناوينه.');
            }
        }

        return $map;
    }

    /**
     * Excel numbers as typed: Arabic digits, Arabic decimal mark, thousands separators ("1,250" or "1 250").
     */
    protected function number(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtr($value, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', '٬' => '', ' ' => '']);

        // "1,250" is a thousand; "1,5" is a decimal comma.
        if (str_contains($value, ',') && ! str_contains($value, '.')) {
            return preg_match('/^\d{1,3}(,\d{3})+$/', $value) === 1 ? str_replace(',', '', $value) : str_replace(',', '.', $value);
        }

        return str_replace(',', '', $value);
    }
}
