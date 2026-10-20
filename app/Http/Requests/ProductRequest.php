<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Support\Rules\ProductRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Data shape of a product (FR-07, SRS 7). Create and update differ only in permission and in the
 * product ignored by the barcode uniqueness check. The rules themselves live in `ProductRules`,
 * shared with the CSV import.
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
        return ProductRules::rules($this->user()->company_id, $this->editedProduct(), $this->input('unit'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ProductRules::attributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ProductRules::messages();
    }
}
