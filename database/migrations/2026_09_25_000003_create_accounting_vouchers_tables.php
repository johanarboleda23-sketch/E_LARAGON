<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->unsignedTinyInteger('class');
            $table->string('nature', 10);
            $table->boolean('allows_posting')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('accounting_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_type', 40);
            $table->string('consecutive', 50)->unique();
            $table->date('voucher_date');
            $table->string('third_party')->nullable();
            $table->text('description')->nullable();
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('accounting_voucher_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chart_of_account_id')->constrained()->restrictOnDelete();
            $table->string('detail')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->timestamps();
        });

        $accounts = [
            ['1000', 'ACTIVO', 1, 'debit', false],
            ['1105', 'Caja', 1, 'debit', true],
            ['1110', 'Bancos', 1, 'debit', true],
            ['1305', 'Clientes', 1, 'debit', true],
            ['1355', 'Anticipos de impuestos y contribuciones', 1, 'debit', true],
            ['1435', 'Mercancías no fabricadas por la empresa', 1, 'debit', true],
            ['1504', 'Terrenos', 1, 'debit', true],
            ['1520', 'Maquinaria y equipo', 1, 'debit', true],
            ['1705', 'Gastos pagados por anticipado', 1, 'debit', true],
            ['2000', 'PASIVO', 2, 'credit', false],
            ['2105', 'Bancos nacionales', 2, 'credit', true],
            ['2205', 'Nacionales - Proveedores', 2, 'credit', true],
            ['2335', 'Costos y gastos por pagar', 2, 'credit', true],
            ['2365', 'Retención en la fuente', 2, 'credit', true],
            ['2367', 'Impuesto a las ventas retenido', 2, 'credit', true],
            ['2408', 'Impuesto sobre las ventas por pagar', 2, 'credit', true],
            ['2505', 'Salarios por pagar', 2, 'credit', true],
            ['2510', 'Cesantías consolidadas', 2, 'credit', true],
            ['2515', 'Intereses sobre cesantías', 2, 'credit', true],
            ['2520', 'Prima de servicios', 2, 'credit', true],
            ['2525', 'Vacaciones consolidadas', 2, 'credit', true],
            ['2605', 'Para obligaciones laborales', 2, 'credit', true],
            ['3000', 'PATRIMONIO', 3, 'credit', false],
            ['3105', 'Capital suscrito y pagado', 3, 'credit', true],
            ['3305', 'Reservas obligatorias', 3, 'credit', true],
            ['3605', 'Utilidad del ejercicio', 3, 'credit', true],
            ['4000', 'INGRESOS', 4, 'credit', false],
            ['4135', 'Comercio al por mayor y al por menor', 4, 'credit', true],
            ['4205', 'Otras ventas', 4, 'credit', true],
            ['5000', 'GASTOS', 5, 'debit', false],
            ['5105', 'Gastos de personal', 5, 'debit', true],
            ['5110', 'Honorarios', 5, 'debit', true],
            ['5115', 'Impuestos', 5, 'debit', true],
            ['5120', 'Arrendamientos', 5, 'debit', true],
            ['5135', 'Servicios', 5, 'debit', true],
            ['5140', 'Gastos legales', 5, 'debit', true],
            ['5150', 'Adecuación e instalación', 5, 'debit', true],
            ['5160', 'Depreciaciones', 5, 'debit', true],
            ['5195', 'Diversos', 5, 'debit', true],
            ['6000', 'COSTOS DE VENTAS', 6, 'debit', false],
            ['6135', 'Comercio al por mayor y al por menor', 6, 'debit', true],
            ['7000', 'COSTOS DE PRODUCCIÓN', 7, 'debit', false],
            ['7205', 'Mano de obra directa', 7, 'debit', true],
            ['7305', 'Costos indirectos', 7, 'debit', true],
            ['8000', 'CUENTAS DE ORDEN DEUDORAS', 8, 'debit', false],
            ['8105', 'Bienes y valores entregados en custodia', 8, 'debit', true],
            ['9000', 'CUENTAS DE ORDEN ACREEDORAS', 9, 'credit', false],
            ['9105', 'Bienes y valores recibidos en custodia', 9, 'credit', true],
        ];

        foreach ($accounts as [$code, $name, $class, $nature, $allowsPosting]) {
            DB::table('chart_of_accounts')->insert([
                'code' => $code,
                'name' => $name,
                'class' => $class,
                'nature' => $nature,
                'allows_posting' => $allowsPosting,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_voucher_lines');
        Schema::dropIfExists('accounting_vouchers');
        Schema::dropIfExists('chart_of_accounts');
    }
};
