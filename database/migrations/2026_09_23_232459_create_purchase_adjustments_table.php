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
    Schema::create('purchase_adjustments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('purchase_id')->constrained()->onDelete('cascade');
        $table->string('type'); // 'credito' (Descuentos/Devolución) o 'debito' (Aumento de valor)
        $table->string('adjustment_number'); // Número consecutivo de la nota interna
        $table->decimal('subtotal_value', 12, 2); // Valor antes de IVA devuelto
        $table->decimal('iva_value', 12, 2)->default(0); // IVA devuelto o ajustado
        $table->decimal('total_value', 12, 2); // Neto total que afecta la factura original
        $table->text('reason')->nullable(); // Concepto o motivo comercial del ajuste
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_adjustments');
    }
};
