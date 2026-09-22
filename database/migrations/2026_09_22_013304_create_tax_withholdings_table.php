<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxWithholdingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('tax_withholdings')->insert([
            [
                'concept' => 'Compras generales (Declarantes)',
                'base_pesos' => 1272000.00, // Base mínima para compras
                'percentage' => 2.50,       // 2.5%
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'concept' => 'Compras generales (No Declarantes)',
                'base_pesos' => 1272000.00, // Base mínima para compras
                'percentage' => 3.50,       // 3.5%
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'concept' => 'Servicios generales (Declarantes)',
                'base_pesos' => 170000.00,  // Base mínima para servicios
                'percentage' => 4.00,       // 4%
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'concept' => 'Servicios generales (No Declarantes)',
                'base_pesos' => 170000.00,  // Base mínima para servicios
                'percentage' => 6.00,       // 6%
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'concept' => 'ReteIVA',
                'base_pesos' => 0.00,       // Se calcula sobre el valor del IVA generado
                'percentage' => 15.00,      // 15% del valor del IVA
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}