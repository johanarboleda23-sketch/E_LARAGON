<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Account::create([
            'name' => 'Cuenta de Ahorros',
            'balance' => 1000.00,
            'account_type' => 'Ahorros',
        ]);

        Account::create([
            'name' => 'Cuenta Corriente',
            'balance' => 5000.00,
            'account_type' => 'Corriente',
        ]);

        Account::create([
            'name' => 'Cuenta de Inversión',
            'balance' => 15000.00,
            'account_type' => 'Inversión',
        ]);
    }
}
