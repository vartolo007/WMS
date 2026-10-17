<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Operation;
use App\Models\Product;
use App\Support\Money;
use Carbon\CarbonInterface;

/**
 * Balances are never stored (golden rule 8): they are SUM(qty_in) − SUM(qty_out) over `operations`.
 */
class StockService
{
    /**
     * Balance of one product in one warehouse, optionally up to and including a date.
     */
    public function balance(int $productId, int $warehouseId, ?CarbonInterface $asOf = null): string
    {
        return $this->balances([$productId], $warehouseId, $asOf)[$productId] ?? Money::quantity('0');
    }

    /**
     * Balances of several products in one warehouse, one query. Every requested id is in the result.
     *
     * @param  list<int>  $productIds
     * @return array<int, string> product id => balance
     */
    public function balances(array $productIds, int $warehouseId, ?CarbonInterface $asOf = null): array
    {
        $rows = Operation::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $productIds)
            ->when($asOf, fn ($query) => $query->whereDate('date', '<=', $asOf->toDateString()))
            ->groupBy('product_id')
            ->selectRaw('product_id, COALESCE(SUM(qty_in), 0) - COALESCE(SUM(qty_out), 0) AS balance')
            ->pluck('balance', 'product_id');

        $result = [];

        foreach ($productIds as $productId) {
            $result[$productId] = Money::quantity((string) ($rows[$productId] ?? '0'));
        }

        return $result;
    }

    /**
     * Lock the involved product rows (ordered by id, so two requests cannot deadlock) and check
     * that the warehouse holds enough of each (golden rule 5). The same product may appear on
     * several lines: quantities are added up first. Must run inside the movement transaction.
     *
     * @param  list<array{product_id: int, quantity: string}>  $items
     *
     * @throws InsufficientStockException
     */
    public function lockAndCheck(array $items, int $warehouseId): void
    {
        $needed = [];

        foreach ($items as $item) {
            $needed[$item['product_id']] = Money::sumQuantity([$needed[$item['product_id']] ?? '0', $item['quantity']]);
        }

        ksort($needed);

        Product::query()->whereIn('id', array_keys($needed))->orderBy('id')->lockForUpdate()->get(['id']);

        $balances = $this->balances(array_keys($needed), $warehouseId);

        foreach ($needed as $productId => $quantity) {
            if (Money::compare($quantity, $balances[$productId]) > 0) {
                throw new InsufficientStockException($productId, $balances[$productId], $quantity);
            }
        }
    }
}
