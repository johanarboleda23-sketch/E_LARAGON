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
        Schema::create('cash_register_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fund_type')->default('general'); // general | menor (caja menor)
            $table->string('status')->default('open'); // open | closed
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->decimal('opening_balance', 18, 2);
            $table->decimal('cash_in', 18, 2)->default(0);
            $table->decimal('cash_out', 18, 2)->default(0);
            $table->decimal('expected_cash', 18, 2)->nullable();
            $table->decimal('counted_cash', 18, 2)->nullable();
            $table->decimal('difference', 18, 2)->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_register_sessions');
    }
};
