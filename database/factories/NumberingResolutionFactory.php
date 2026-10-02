<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\NumberingResolution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NumberingResolution>
 */
class NumberingResolutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'document_type' => 'sale',
            'prefix' => 'FV',
            'resolution_number' => fake()->unique()->numerify('18764########'),
            'resolution_date' => now()->subMonth()->toDateString(),
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'range_from' => 1,
            'range_to' => 5000,
            'next_number' => 1,
            'active' => true,
        ];
    }
}
