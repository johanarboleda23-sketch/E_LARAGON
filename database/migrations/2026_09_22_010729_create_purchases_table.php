<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('purchases', function (Blueprint $table) {
        $table->id();
        $table->string('invoice_number'); // Número de Factura
        $table->string('provider'); // Proveedor
        $table->date('purchase_date'); // Fecha
        $table->decimal('tax_percentage', 5, 2)->default(19.00); // IVA (Ej: 19% o 16%)
        $table->decimal('total', 12, 2)->default(0);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
