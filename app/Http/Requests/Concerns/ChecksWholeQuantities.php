<?php

namespace App\Http\Requests\Concerns;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Contracts\Validation\Validator;

/**
 * A unit that takes no fractions (box, piece) takes whole quantities only (SRS 3.4.2, 13.2.14).
 * For requests with `items.*.product_id` and `items.*.quantity`.
 */
trait ChecksWholeQuantities
{
    protected function checkWholeQuantities(Validator $validator): void
    {
        $items = (array) $this->input('items', []);
        $products = Product::query()->whereIn('id', array_filter(array_column($items, 'product_id'), 'is_numeric'))->get()->keyBy('id');

        foreach ($items as $index => $item) {
            $product = $products->get($item['product_id'] ?? null);
            $quantity = (string) ($item['quantity'] ?? '');

            if ($product === null || ! is_numeric($quantity) || $product->allowsFraction()) {
                continue;
            }

            if (Money::compare($quantity, (string) (int) $quantity) !== 0) {
                $validator->errors()->add("items.{$index}.quantity", 'هالوحدة ما بتقبل كسور، اكتب عدد صحيح.');
            }
        }
    }
}
