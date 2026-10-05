<?php

namespace Database\Factories;

use App\Enums\CompanyStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'status' => CompanyStatus::Trial,
            'trial_ends_at' => today()->addDays(config('wms.trial_days')),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => CompanyStatus::Active]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => CompanyStatus::Suspended]);
    }

    public function trialExpired(): static
    {
        return $this->state(fn () => [
            'status' => CompanyStatus::Trial,
            'trial_ends_at' => today()->subDays(3),
        ]);
    }
}
