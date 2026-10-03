<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factus_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('environment')->default('sandbox');
            $table->string('client_id');
            $table->text('client_secret');
            $table->string('username');
            $table->text('password');
            $table->unsignedBigInteger('invoice_numbering_range_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factus_credentials');
    }
};
