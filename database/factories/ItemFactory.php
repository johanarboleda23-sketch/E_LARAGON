<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'producto',
            'base_unit' => 'unidad',
            'name' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->bothify('SKU-####'),
            'sale_price' => fake()->randomFloat(2, 1, 100000),
            'purchase_price' => fake()->randomFloat(2, 1, 50000),
            'stock' => 0,
            'min_stock' => 0,
        ];
    }
}
