<?php

namespace App\Services;

use App\Models\Party;
use App\Support\Rules\PartyRules;

/**
 * Customers and suppliers from a CSV file (FR-08). Columns: الاسم*, النوع* (عميل / مورد), الهاتف.
 */
class PartyImportService extends CatalogImportService
{
    protected function columns(): array
    {
        return [
            'name' => ['name', 'الاسم', 'اسم الطرف'],
            'type' => ['type', 'النوع'],
            'phone' => ['phone', 'الهاتف', 'رقم الهاتف', 'الموبايل'],
        ];
    }

    protected function requiredColumns(): array
    {
        return ['name', 'type'];
    }

    protected function normalize(array $row): array
    {
        $type = isset($row['type']) ? mb_strtolower(trim($row['type'])) : null;
        $row['type'] = match ($type) {
            'عميل', 'زبون', 'customer' => 'customer',
            'مورد', 'مورّد', 'supplier' => 'supplier',
            default => $row['type'] ?? null,
        };

        return $row;
    }

    protected function rules(array $row): array
    {
        return PartyRules::rules();
    }

    protected function attributes(): array
    {
        return PartyRules::attributes();
    }

    protected function messages(): array
    {
        return PartyRules::messages();
    }

    protected function create(array $row): void
    {
        Party::create(array_filter($row, fn ($value) => $value !== null));
    }

    public function plural(): string
    {
        return 'العملاء والموردين';
    }

    public function help(): array
    {
        return [
            ['الاسم', true, 'محل أبو خالد'],
            ['النوع', true, 'عميل (أو مورد)'],
            ['الهاتف', false, '0944 111 222'],
        ];
    }

    public function sample(): string
    {
        return "\xEF\xBB\xBF".implode("\r\n", [
            'الاسم,النوع,الهاتف',
            'محل أبو خالد,عميل,0944 111 222',
            'مؤسسة الشام للتوريد,مورد,0933 555 666',
        ])."\r\n";
    }
}
