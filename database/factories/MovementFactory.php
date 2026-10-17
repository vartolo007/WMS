<?php

namespace Database\Factories;

use App\Enums\MovementStatus;
use App\Enums\MovementType;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a bare movement header. It does NOT post anything: stock-affecting tests go through MovementService.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Movement>
 */
class MovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'warehouse_id' => fn (array $attributes) => User::factory()->warehouse()->create(['company_id' => $attributes['company_id']])->id,
            'target_warehouse_id' => null,
            'type' => MovementType::Sale,
            'number' => fake()->unique()->numberBetween(1, 1_000_000),
            'document_no' => null,
            'status' => MovementStatus::Confirmed,
            'related_movement_id' => null,
            'party_id' => null,
            'date' => today()->toDateString(),
            'subtotal' => '0.00',
            'discount' => '0.00',
            'total' => '0.00',
            'created_by' => fn (array $attributes) => $attributes['warehouse_id'],
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => MovementStatus::Cancelled]);
    }
}
