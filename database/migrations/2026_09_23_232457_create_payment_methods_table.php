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
    Schema::create('payment_methods', function (Blueprint $table) {
        $table->id();
        $table->string('name'); // Ej: 'Efectivo', 'Bancos (Bancolombia)', 'Cruzar con Anticipo', 'Crédito'
        $table->boolean('is_editable')->default(true); // Permite saber si el usuario lo puede borrar
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
