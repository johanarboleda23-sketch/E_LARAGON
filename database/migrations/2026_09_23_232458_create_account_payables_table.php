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
    Schema::create('account_payables', function (Blueprint $table) {
        $table->id();
        $table->foreignId('purchase_id')->constrained()->onDelete('cascade'); // Enlace a la factura
        $table->decimal('total_debt', 12, 2); // Valor neto total de la deuda original
        $table->decimal('total_paid', 12, 2)->default(0); // Lo que se ha abonado con egresos
        $table->decimal('current_balance', 12, 2); // Saldo pendiente por pagar en cartera
        $table->integer('credit_days')->default(0); // Días de crédito autorizados (Ej: 30, 60 días)
        $table->date('due_date')->nullable(); // Fecha de vencimiento contable de la obligación
        $table->string('status')->default('pendiente'); // 'pendiente', 'parcial', 'pagado'
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_payables');
    }
};
