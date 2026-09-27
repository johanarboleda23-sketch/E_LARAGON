<?php

namespace Database\Factories;

use App\Models\FinancialMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialMovementFactory extends Factory
{
    protected $model = FinancialMovement::class;

    public function definition()
    {
        return [
            'account_id' => $this->faker->numberBetween(1, 100), // Assuming you have 100 accounts
            'amount' => $this->faker->randomFloat(2, 1, 10000), // Random amount between 1 and 10000
            'type' => $this->faker->randomElement(['income', 'expense']),
            'description' => $this->faker->sentence(),
            'created_at' => $this->faker->dateTimeThisYear(),
            'updated_at' => $this->faker->dateTimeThisYear(),
        ];
    }
}
