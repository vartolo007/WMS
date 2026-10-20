<?php

namespace App\Services;

use App\Models\Movement;
use App\Models\Operation;
use App\Models\Scopes\WarehouseScope;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The ONLY writer of the `operations` table (golden rule 1, FR-18).
 * Called by MovementService inside the movement's transaction.
 */
class PostingService
{
    /**
     * One row per item per effect of the movement type (SRS 6.1). Each row carries its value before
     * the discount and its net value after its share of the discount (SRS 6.2).
     */
    public function post(Movement $movement): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Posting must run inside the movement transaction.');
        }

        $items = $movement->items()->orderBy('id')->get();
        $nets = $this->netValues($items->pluck('line_total', 'id')->all(), (string) $movement->subtotal, (string) $movement->total);

        foreach ($items as $item) {
            foreach ($movement->type->effects() as [$field, $sign]) {
                (new Operation)->forceFill([
                    'company_id' => $movement->company_id,
                    'warehouse_id' => $movement->{$field},
                    'movement_id' => $movement->id,
                    'movement_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'party_id' => $movement->party_id,
                    'type' => $movement->type,
                    'date' => $movement->date,
                    'qty_in' => $sign > 0 ? $item->quantity : Money::quantity('0'),
                    'qty_out' => $sign < 0 ? $item->quantity : Money::quantity('0'),
                    'unit_price' => $item->unit_price,
                    'value' => $item->line_total,
                    'net_value' => $nets[$item->id],
                ])->save();
            }
        }
    }

    /**
     * Remove the rows of a movement (cancellation, decision Q5). The warehouse scope is lifted: whoever cancels
     * a transfer removes both of its rows, the one in the receiving warehouse too.
     */
    public function unpost(Movement $movement): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Unposting must run inside the cancellation transaction.');
        }

        Operation::withoutGlobalScope(WarehouseScope::class)->where('movement_id', $movement->id)->delete();
    }

    /**
     * Net value of every line after its share of the movement discount.
     * Lines 1..n-1: value × total ÷ subtotal rounded to 2 decimals; the last line takes the rest, so the nets
     * add up to the movement total exactly. With a zero subtotal nothing is shared.
     *
     * @param  array<int, string>  $values  item id => line total, in posting order
     * @return array<int, string> item id => net value
     */
    private function netValues(array $values, string $subtotal, string $total): array
    {
        $nets = [];
        $lastId = array_key_last($values);

        if (Money::compare($subtotal, '0') === 0) {
            foreach ($values as $id => $value) {
                $nets[$id] = Money::money($value);
            }

            return $nets;
        }

        foreach ($values as $id => $value) {
            $nets[$id] = $id === $lastId
                ? Money::subtract($total, Money::sum($nets))
                : Money::ratio($value, $total, $subtotal);
        }

        return $nets;
    }
}
