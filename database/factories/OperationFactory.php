<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\Movement;
use App\Models\MovementItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only shortcut for isolation tests. The application itself writes operations
 * only through PostingService (golden rule 1).
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Operation>
 */
class OperationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'movement_item_id' => MovementItem::factory(),
            'movement_id' => fn (array $a) => MovementItem::withoutGlobalScopes()->find($a['movement_item_id'])->movement_id,
            'company_id' => fn (array $a) => Movement::withoutGlobalScopes()->find($a['movement_id'])->company_id,
            'warehouse_id' => fn (array $a) => Movement::withoutGlobalScopes()->find($a['movement_id'])->warehouse_id,
            'product_id' => fn (array $a) => MovementItem::withoutGlobalScopes()->find($a['movement_item_id'])->product_id,
            'party_id' => null,
            'type' => MovementType::Purchase,
            'date' => today()->toDateString(),
            'qty_in' => '1.000',
            'qty_out' => '0.000',
            'unit_price' => '10.00',
            'value' => '10.00',
            'net_value' => '10.00',
        ];
    }
}
