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
        $table->string('provider'); // Proveedor / Nit
        $table->date('purchase_date'); // Fecha de la factura
        $table->decimal('subtotal', 12, 2)->default(0); // Valor antes de impuestos
        $table->decimal('iva_total', 12, 2)->default(0); // IVA calculado
        $table->decimal('retefuente', 12, 2)->default(0); // Retención en la fuente
        $table->decimal('total_pagar', 12, 2)->default(0); // Neto real a pagar
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
