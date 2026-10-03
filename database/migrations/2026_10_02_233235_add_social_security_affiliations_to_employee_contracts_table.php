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
        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->string('eps_name')->nullable()->after('contract_type');
            $table->string('afp_name')->nullable()->after('eps_name');
            $table->unsignedTinyInteger('arl_risk_class')->default(1)->after('afp_name');
            $table->string('compensation_fund_name')->nullable()->after('arl_risk_class');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table) {
            $table->dropColumn(['eps_name', 'afp_name', 'arl_risk_class', 'compensation_fund_name']);
        });
    }
};
