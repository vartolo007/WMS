<?php

namespace App\Http\Requests;

use App\Models\Product;

class StoreProductRequest extends ProductRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }
}
