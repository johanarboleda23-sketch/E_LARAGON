<?php

namespace Database\Seeders;

use App\Models\FinancialMovement;
use Illuminate\Database\Seeder;

class FinancialMovementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        FinancialMovement::create([
            'account_id' => 1,
            'amount' => 1000,
            'type' => 'income',
            'description' => 'Initial deposit',
            'date' => now(),
        ]);

        FinancialMovement::create([
            'account_id' => 1,
            'amount' => -200,
            'type' => 'expense',
            'description' => 'Office supplies',
            'date' => now()->subDays(5),
        ]);

        FinancialMovement::create([
            'account_id' => 2,
            'amount' => 1500,
            'type' => 'income',
            'description' => 'Freelance project payment',
            'date' => now()->subDays(10),
        ]);

        FinancialMovement::create([
            'account_id' => 2,
            'amount' => -300,
            'type' => 'expense',
            'description' => 'Software subscription',
            'date' => now()->subDays(15),
        ]);
    }
}
