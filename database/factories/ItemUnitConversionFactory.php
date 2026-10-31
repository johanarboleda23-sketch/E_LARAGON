<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemUnitConversion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemUnitConversion>
 */
class ItemUnitConversionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => session('company_id'),
            'item_id' => Item::factory(),
            'name' => fake()->unique()->word(),
            'factor' => 6,
            'sale_price' => null,
        ];
    }
}
