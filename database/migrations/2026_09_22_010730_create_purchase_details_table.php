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
    Schema::create('purchase_details', function (Blueprint $table) {
        $table->id();
        $table->foreignId('purchase_id')->constrained()->onDelete('cascade');
        $table->foreignId('item_id')->constrained()->onDelete('cascade'); // Producto o servicio comprado
        $table->integer('quantity'); // Cantidad que ingresa al stock
        $table->decimal('cost_price', 12, 2); // Costo unitario antes de IVA
        $table->decimal('iva_percentage', 5, 2); // El usuario podrá elegir: 19, 5, 16 o 0
        $table->decimal('iva_value', 12, 2); // Valor del IVA calculado por unidad
        $table->decimal('utility_percentage', 5, 2); // Porcentaje de ganancia deseado (Ej: 30%, 50%)
        $table->decimal('calculated_sale_price', 12, 2); // El precio de venta final calculado automáticamente
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_details');
    }
};
