<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\Company;
use App\Models\Operation;
use App\Models\Product;
use App\Models\Scopes\WarehouseScope;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

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
     * Last purchase prices of many products with one query (the products list). Products that were
     * never bought are missing from the result.
     *
     * @param  list<int>  $productIds
     * @return array<int, string> product id => unit price
     */
    public function lastPurchasePrices(array $productIds): array
    {
        $ranked = Operation::query()
            ->withoutGlobalScope(WarehouseScope::class)
            ->where('type', MovementType::Purchase)
            ->whereIn('product_id', $productIds)
            ->selectRaw('product_id, unit_price, ROW_NUMBER() OVER (PARTITION BY product_id ORDER BY date DESC, id DESC) AS price_rank');

        return DB::query()->fromSub($ranked, 'ranked')
            ->where('price_rank', 1)
            ->pluck('unit_price', 'product_id')
            ->map(fn ($price) => Money::money((string) $price))
            ->all();
    }

    /**
     * The suggested price of every product on a page, same rule as `suggestedPrice()` (null = none).
     *
     * @param  iterable<Product>  $products
     * @return array<int, ?string> product id => suggested price
     */
    public function suggestedPrices(iterable $products): array
    {
        $products = collect($products);
        $last = $this->lastPurchasePrices($products->pluck('id')->all());
        $markups = $products->pluck('company_id')->unique()->mapWithKeys(
            fn (int $companyId) => [$companyId => (string) Company::query()->whereKey($companyId)->value('markup_percent')]
        );

        return $products->mapWithKeys(fn (Product $product) => [
            $product->id => match (true) {
                $product->custom_sale_price !== null => Money::money((string) $product->custom_sale_price),
                isset($last[$product->id]) => Money::percent($last[$product->id], $markups[$product->company_id]),
                default => null,
            },
        ])->all();
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
