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
        if (Schema::hasTable('payroll_social_security_errors')) {
            $indexes = collect(Schema::getIndexes('payroll_social_security_errors'))->pluck('name');
            if (! $indexes->contains('pila_ss_run_status_idx')) {
                Schema::table('payroll_social_security_errors', function (Blueprint $table) {
                    $table->index(['company_id', 'payroll_run_id', 'resolved'], 'pila_ss_run_status_idx');
                });
            }

            return;
        }

        Schema::create('payroll_social_security_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('third_party_id')->nullable()->constrained('third_parties')->nullOnDelete();
            $table->unsignedInteger('line_number')->nullable();
            $table->string('field')->nullable();
            $table->text('message');
            $table->boolean('resolved')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'payroll_run_id', 'resolved'], 'pila_ss_run_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_social_security_errors');
    }
};
