<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition()
    {
        return [
            'name' => $this->faker->company,
            'balance' => $this->faker->randomFloat(2, 100, 10000),
            'currency' => $this->faker->currencyCode,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
