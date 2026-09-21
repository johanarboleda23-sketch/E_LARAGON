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
    Schema::create('items', function (Blueprint $table) {
        $table->id();
        $table->string('type'); // 'producto' o 'servicio'
        $table->string('name');
        $table->string('code')->nullable(); // Código o SKU opcional
        $table->decimal('sale_price', 10, 2);
        $table->decimal('purchase_price', 10, 2)->nullable(); // Solo para productos
        $table->integer('stock')->default(0); // Inventario inicial
        $table->integer('min_stock')->default(0); // Alerta de stock mínimo
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
