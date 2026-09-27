<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('third_party_id')->constrained()->restrictOnDelete();
            $table->string('consecutive', 60);
            $table->date('document_date');
            $table->string('concept');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('iva_total', 15, 2)->default(0);
            $table->string('withholding_concept')->default('none');
            $table->decimal('retention_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'consecutive']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_documents');
    }
};
