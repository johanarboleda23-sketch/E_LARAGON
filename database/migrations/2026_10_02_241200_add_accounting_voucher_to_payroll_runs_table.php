<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->foreignId('accounting_voucher_id')->nullable()->after('total_employer_cost')->constrained('accounting_vouchers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropForeign(['accounting_voucher_id']);
            $table->dropColumn('accounting_voucher_id');
        });
    }
};
