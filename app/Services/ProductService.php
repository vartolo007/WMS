<?php

namespace App\Services;

use App\Models\MovementItem;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * The one business rule of the catalogue that needs database state: a product that has movements
 * keeps its unit, because its stored quantities were saved in that unit.
 */
class ProductService
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(Product $product, array $data): Product
    {
        if (isset($data['unit']) && $data['unit'] !== $product->unit && MovementItem::query()->where('product_id', $product->id)->exists()) {
            throw ValidationException::withMessages(['unit' => 'ما بتقدر تغيّر الوحدة لأنه في حركات على هالمادة.']);
        }

        $product->update($data);

        return $product;
    }
}
