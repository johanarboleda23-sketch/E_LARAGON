<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResolutionSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('resolutions')->insert([
            [
                'type' => 'compra',
                'name' => 'Control de Consecutivo Compras Internas',
                'prefix' => 'COM',
                'start_number' => 1,
                'end_number' => 999999,
                'current_number' => 1, // Arranca en el 1
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}