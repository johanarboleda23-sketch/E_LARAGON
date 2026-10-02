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
        Schema::create('numbering_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 60);
            $table->string('prefix', 20)->nullable();
            $table->string('resolution_number', 50);
            $table->date('resolution_date')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedBigInteger('range_from');
            $table->unsignedBigInteger('range_to');
            $table->unsignedBigInteger('next_number');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'document_type', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('numbering_resolutions');
    }
};
