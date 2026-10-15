<?php

namespace Database\Factories;

use App\Enums\PartyType;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Party>
 */
class PartyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->name(),
            'phone' => null,
            'type' => PartyType::Customer,
            'is_active' => true,
        ];
    }

    public function supplier(): static
    {
        return $this->state(fn () => ['type' => PartyType::Supplier]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
