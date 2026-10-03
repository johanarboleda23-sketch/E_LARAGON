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
        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->decimal('arl_employer', 15, 2)->default(0)->after('pension_employer');
            $table->unsignedTinyInteger('arl_risk_class')->default(1)->after('arl_employer');
            $table->decimal('sena', 15, 2)->default(0)->after('arl_risk_class');
            $table->decimal('icbf', 15, 2)->default(0)->after('sena');
            $table->decimal('compensation_fund', 15, 2)->default(0)->after('icbf');
            $table->string('eps_name')->nullable()->after('compensation_fund');
            $table->string('afp_name')->nullable()->after('eps_name');
            $table->string('compensation_fund_name')->nullable()->after('afp_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->dropColumn(['arl_employer', 'arl_risk_class', 'sena', 'icbf', 'compensation_fund', 'eps_name', 'afp_name', 'compensation_fund_name']);
        });
    }
};
