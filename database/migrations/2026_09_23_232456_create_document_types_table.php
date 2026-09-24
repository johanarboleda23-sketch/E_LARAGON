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
    Schema::create('document_types', function (Blueprint $table) {
        $table->id();
        $table->string('name'); // Ej: 'Factura de Compra Contado', 'Factura Crédito', 'Documento Soporte'
        $table->string('prefix'); // Ej: 'FCC', 'FCR', 'DS'
        $table->integer('current_number')->default(1); // Consecutivo independiente por documento
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
