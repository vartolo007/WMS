<?php

namespace Database\Factories;

use App\Models\Movement;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MovementItem>
 */
class MovementItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'movement_id' => Movement::factory(),
            'product_id' => fn (array $attributes) => Product::factory()->create([
                'company_id' => Movement::withoutGlobalScopes()->find($attributes['movement_id'])->company_id,
            ])->id,
            'quantity' => '1.000',
            'unit_price' => '10.00',
            'line_total' => '10.00',
        ];
    }
}
