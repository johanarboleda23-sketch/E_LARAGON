<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('third_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('natural');
            $table->string('name');
            $table->string('document')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_customer')->default(false);
            $table->boolean('is_supplier')->default(false);
            $table->boolean('is_employee')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->date('payment_date');
            $table->string('status')->default('calculated');
            $table->decimal('total_gross', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('total_net', 15, 2)->default(0);
            $table->decimal('total_employer_cost', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('third_party_id')->constrained()->restrictOnDelete();
            $table->decimal('salary', 15, 2);
            $table->decimal('health_employee', 15, 2)->default(0);
            $table->decimal('pension_employee', 15, 2)->default(0);
            $table->decimal('solidarity_employee', 15, 2)->default(0);
            $table->decimal('withholding', 15, 2)->default(0);
            $table->decimal('net_pay', 15, 2);
            $table->decimal('health_employer', 15, 2)->default(0);
            $table->decimal('pension_employer', 15, 2)->default(0);
            $table->decimal('parafiscals', 15, 2)->default(0);
            $table->decimal('severance_provision', 15, 2)->default(0);
            $table->decimal('service_bonus_provision', 15, 2)->default(0);
            $table->decimal('vacation_provision', 15, 2)->default(0);
            $table->decimal('employer_cost', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('third_parties');
    }
};
