<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\Operation;
use App\Models\Product;
use App\Models\Scopes\WarehouseScope;
use App\Support\Money;

/**
 * Sale price rule (FR-14): the manual price if the product has one, otherwise
 * last purchase price × markup ÷ 100.
 */
class PricingService
{
    /**
     * Unit price of the latest purchase row of the product in the whole company (SRS 3.4.5).
     * Only purchases count (returns and transfers are ignored), from every warehouse, so a
     * warehouse user's own-warehouse scope is lifted for this one read.
     */
    public function lastPurchasePrice(Product $product): ?string
    {
        $price = Operation::query()
            ->withoutGlobalScope(WarehouseScope::class)
            ->where('product_id', $product->id)
            ->where('type', MovementType::Purchase)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->value('unit_price');

        return $price === null ? null : Money::money((string) $price);
    }

    /**
     * Null when the product has neither a manual price nor a purchase to base one on.
     */
    public function suggestedPrice(Product $product): ?string
    {
        if ($product->custom_sale_price !== null) {
            return Money::money((string) $product->custom_sale_price);
        }

        $last = $this->lastPurchasePrice($product);

        if ($last === null) {
            return null;
        }

        return Money::percent($last, (string) $product->company->markup_percent);
    }
}
