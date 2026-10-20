<?php

namespace App\Services;

use App\Enums\MovementStatus;
use App\Models\Movement;
use App\Models\MovementItem;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * What can still be returned from an invoice, and what a return is worth (FR-15, SRS 3.4.6 / 6.2).
 * An invoice holds each product once, so a product is the "original item" of a return.
 * Only confirmed returns count: a cancelled return gives its quantity back.
 */
class ReturnService
{
    /**
     * One entry per product of the original invoice, keyed by product id.
     *
     * @return Collection<int, array{product: Product, product_id: int, quantity: string, unit_price: string, returned: string, returnable: string}>
     */
    public function lines(Movement $original): Collection
    {
        $returned = MovementItem::query()
            ->join('movements', 'movements.id', '=', 'movement_items.movement_id')
            ->where('movements.related_movement_id', $original->id)
            ->where('movements.status', MovementStatus::Confirmed->value)
            ->groupBy('movement_items.product_id')
            ->selectRaw('movement_items.product_id AS product_id, SUM(movement_items.quantity) AS returned')
            ->pluck('returned', 'product_id');

        return $original->items()->with('product')->orderBy('id')->get()
            ->map(function (MovementItem $item) use ($returned) {
                $done = Money::quantity((string) ($returned[$item->product_id] ?? '0'));

                return [
                    'product' => $item->product,
                    'product_id' => $item->product_id,
                    'quantity' => Money::quantity((string) $item->quantity),
                    'unit_price' => Money::money((string) $item->unit_price),
                    'returned' => $done,
                    'returnable' => Money::subtractQuantity((string) $item->quantity, $done),
                ];
            })
            ->keyBy('product_id');
    }

    /**
     * A confirmed purchase or sale that still has something left to return.
     */
    public function isReturnable(Movement $original): bool
    {
        return $original->status === MovementStatus::Confirmed
            && $original->type->returnType() !== null
            && $this->anyReturnable($this->lines($original));
    }

    /**
     * Is there something left to return in lines already loaded by `lines()`?
     *
     * @param  Collection<int, array{returnable: string}>  $lines
     */
    public function anyReturnable(Collection $lines): bool
    {
        return $lines->contains(fn (array $line) => Money::isPositive($line['returnable']));
    }

    /**
     * Header amounts of a return worth `$subtotal` at the original prices: it carries the same share of the
     * original discount as it has of the original subtotal (SRS 6.2: 2,000 of a 10,000 invoice paid 9,000 → 1,800).
     *
     * @return array{subtotal: string, discount: string, total: string}
     */
    public function quote(Movement $original, string $subtotal): array
    {
        $total = Money::ratio($subtotal, (string) $original->total, (string) $original->subtotal);

        return [
            'subtotal' => Money::money($subtotal),
            'discount' => Money::subtract($subtotal, $total),
            'total' => $total,
        ];
    }
}
