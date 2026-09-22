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
    Schema::create('tax_withholdings', function (Blueprint $table) {
        $table->id();
        $table->string('concept'); // Ej: 'Compras generales (Declarantes)', 'Servicios'
        $table->decimal('base_pesos', 12, 2); // Base mínima en pesos colombianos para aplicar la retención
        $table->decimal('percentage', 5, 2); // Porcentaje de retención (Ej: 2.5, 3.5, 4.0, 6.0)
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t');
    }
};
