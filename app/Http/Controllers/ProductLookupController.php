<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductLookupRequest;
use App\Models\Product;
use App\Services\PricingService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;

/**
 * JSON used by the invoice screen when a barcode is scanned or a name is typed (FR-11).
 * Exact barcode first, then the first name match. Prices and balance are decimal strings.
 */
class ProductLookupController extends Controller
{
    public function __invoke(ProductLookupRequest $request, PricingService $pricing, StockService $stock): JsonResponse
    {
        $query = Product::query()->orderByDesc('is_active');

        if ($request->filled('product_id')) {
            $product = $query->find($request->integer('product_id'));
        } else {
            $term = trim((string) $request->query('q'));
            $product = (clone $query)->where('barcode', $term)->first()
                ?? (clone $query)->search($term)->orderBy('name')->first();
        }

        if ($product === null) {
            return response()->json(['message' => 'ما في مادة بهالرمز أو الاسم.'], 404);
        }

        if (! $product->is_active) {
            return response()->json(['message' => "المادة «{$product->name}» موقوفة ولا تقبل حركات جديدة."], 422);
        }

        $warehouseId = $this->warehouseId($request);

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'barcode' => $product->barcode,
            'unit' => $product->unitLabel(),
            'location' => $product->location,
            'allows_fraction' => $product->allowsFraction(),
            'suggested_price' => $pricing->suggestedPrice($product),
            'last_purchase_price' => $pricing->lastPurchasePrice($product),
            'balance' => $warehouseId === null ? null : $stock->balance($product->id, $warehouseId),
        ]);
    }

    /**
     * A warehouse user always sees their own warehouse; the owner's choice must be a warehouse of the company.
     */
    private function warehouseId(ProductLookupRequest $request): ?int
    {
        $user = $request->user();

        if ($user->isWarehouse()) {
            return $user->id;
        }

        return $request->filled('warehouse_id')
            ? $user->company->warehouses()->whereKey($request->integer('warehouse_id'))->value('id')
            : null;
    }
}
