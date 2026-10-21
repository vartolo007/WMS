<?php

namespace App\Services;

use App\Models\Product;
use App\Support\Rules\ProductRules;
use App\Support\Units;
use Illuminate\Support\Facades\Auth;

/**
 * Products from a CSV file (FR-07). Columns: الاسم*, الوحدة*, الباركود, الموقع, الحد الأدنى, سعر البيع.
 */
class ProductImportService extends CatalogImportService
{
    protected function columns(): array
    {
        // The second entry of `name` and `unit` is the header named in the "missing column" message.
        return [
            'name' => ['name', 'الاسم', 'اسم المادة', 'المادة'],
            'unit' => ['unit', 'الوحدة'],
            'barcode' => ['barcode', 'الباركود', 'باركود'],
            'location' => ['location', 'الموقع'],
            'min_stock' => ['min_stock', 'الحد الأدنى', 'الحد الادنى'],
            'custom_sale_price' => ['custom_sale_price', 'سعر البيع', 'سعر البيع اليدوي'],
        ];
    }

    protected function requiredColumns(): array
    {
        return ['name', 'unit'];
    }

    protected function normalize(array $row): array
    {
        // The unit may be written as the Arabic label (لتر) or as the key (liter).
        $labels = array_change_key_case(array_flip(Units::options()), CASE_LOWER);
        $unit = isset($row['unit']) ? mb_strtolower(trim($row['unit'])) : null;
        $row['unit'] = $unit === null ? null : ($labels[$unit] ?? (in_array($unit, Units::keys(), true) ? $unit : $row['unit']));

        $row['min_stock'] = $this->number($row['min_stock'] ?? null);
        $row['custom_sale_price'] = $this->number($row['custom_sale_price'] ?? null);

        return $row;
    }

    protected function rules(array $row): array
    {
        // Barcodes are checked in memory by crossChecks(): one query for the catalogue, not one per row.
        return ProductRules::rules((int) Auth::user()->company_id, null, $row['unit'] ?? null, checkBarcodeUnique: false);
    }

    protected function displayValue(string $field, ?string $value): string
    {
        return $field === 'unit' && $value !== null ? Units::label($value) : parent::displayValue($field, $value);
    }

    protected function attributes(): array
    {
        return ProductRules::attributes();
    }

    protected function messages(): array
    {
        return ProductRules::messages();
    }

    protected function crossChecks(array $row, int $line, array &$state): array
    {
        $barcode = $row['barcode'] ?? null;

        if ($barcode === null) {
            return [];
        }

        // The company scope limits this to the signed-in user's catalogue.
        $state['existing'] ??= array_flip(Product::query()->whereNotNull('barcode')->pluck('barcode')->all());

        if (isset($state['existing'][$barcode])) {
            return ['الباركود «'.$barcode.'» مستخدم لمادة موجودة بالشركة.'];
        }

        if (isset($state['seen'][$barcode])) {
            return ['الباركود «'.$barcode.'» مكرر بالملف (أول مرة بسطر '.$state['seen'][$barcode].').'];
        }

        $state['seen'][$barcode] = $line;

        return [];
    }

    protected function create(array $row): void
    {
        Product::create(array_filter($row, fn ($value) => $value !== null));
    }

    public function plural(): string
    {
        return 'المواد';
    }

    public function help(): array
    {
        return [
            ['الاسم', true, 'زيت زيتون'],
            ['الوحدة', true, 'لتر (أو كيلو، علبة، قطعة)'],
            ['الباركود', false, '6290001000011'],
            ['الموقع', false, 'رف A3'],
            ['الحد الأدنى', false, '20'],
            ['سعر البيع', false, '19999.50'],
        ];
    }

    public function sample(): string
    {
        return "\xEF\xBB\xBF".implode("\r\n", [
            'الاسم,الوحدة,الباركود,الموقع,الحد الأدنى,سعر البيع',
            'زيت زيتون,لتر,6290001000011,رف A3,20,',
            'سكر,كيلو,6290001000028,رف B1,50.5,',
            'معلبات حمص,علبة,6290001000035,,12,6500',
        ])."\r\n";
    }
}
