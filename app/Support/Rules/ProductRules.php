<?php

namespace App\Support\Rules;

use App\Models\Product;
use App\Support\Units;
use Illuminate\Validation\Rule;

/**
 * The data-shape rules of a product (FR-07, SRS 7), in one place: the product form and the CSV import
 * both use them, so a product is valid the same way however it arrives.
 */
class ProductRules
{
    /**
     * @param  bool  $checkBarcodeUnique  false when the caller checks barcodes itself (the import, in memory)
     * @return array<string, mixed>
     */
    public static function rules(int $companyId, ?Product $ignore = null, ?string $unit = null, bool $checkBarcodeUnique = true): array
    {
        // A unit that takes no fractions (box, piece) also takes a whole-number minimum (SRS 13.2.14).
        $wholeMinimum = ! Units::allowsFraction($unit);

        return [
            'name' => ['required', 'string', 'max:255'],
            'barcode' => [
                'nullable', 'string', 'max:64',
                Rule::when($checkBarcodeUnique, Rule::unique('products', 'barcode')->where('company_id', $companyId)->ignore($ignore)),
            ],
            'unit' => ['required', Rule::in(Units::keys())],
            'location' => ['nullable', 'string', 'max:255'],
            'custom_sale_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'min_stock' => [
                'nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999',
                Rule::when($wholeMinimum, ['integer']),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'name' => 'اسم المادة',
            'barcode' => 'الباركود',
            'unit' => 'الوحدة',
            'location' => 'الموقع',
            'custom_sale_price' => 'سعر البيع',
            'min_stock' => 'الحد الأدنى',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'barcode.unique' => 'هالباركود مستخدم لمادة تانية بالشركة.',
            'min_stock.integer' => 'هالوحدة ما بتقبل كسور، اكتب عدد صحيح.',
        ];
    }
}
