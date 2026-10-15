<?php

namespace App\Http\Requests;

use App\Models\Product;

class UpdateProductRequest extends ProductRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    protected function editedProduct(): ?Product
    {
        return $this->route('product');
    }
}
