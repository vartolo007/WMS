<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Shape of the invoice screen's product lookup: a scanned barcode / typed name (`q`), or a known
 * product (`product_id`, to refresh its balance), plus the invoice warehouse for the balance.
 */
class ProductLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Product::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'required_without:product_id', 'string', 'max:255'],
            'product_id' => ['nullable', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
        ];
    }
}
