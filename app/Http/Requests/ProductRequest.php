<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Support\Units;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Data shape of a product (FR-07, SRS 7). Create and update differ only in permission and in the
 * product ignored by the barcode uniqueness check.
 */
abstract class ProductRequest extends FormRequest
{
    /**
     * The product being edited, or null on create.
     */
    protected function editedProduct(): ?Product
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // A unit that takes no fractions (box, piece) also takes a whole-number minimum (SRS 13.2.14).
        $wholeMinimum = ! Units::allowsFraction($this->input('unit'));

        return [
            'name' => ['required', 'string', 'max:255'],
            'barcode' => [
                'nullable', 'string', 'max:64',
                Rule::unique('products', 'barcode')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($this->editedProduct()),
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
    public function attributes(): array
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
    public function messages(): array
    {
        return [
            'barcode.unique' => 'هالباركود مستخدم لمادة تانية بالشركة.',
            'min_stock.integer' => 'هالوحدة ما بتقبل كسور، اكتب عدد صحيح.',
        ];
    }
}
