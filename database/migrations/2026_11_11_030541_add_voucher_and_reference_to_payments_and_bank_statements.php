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
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('created_by')->constrained()->nullOnDelete();
            $table->foreignId('accounting_voucher_id')->nullable()->after('payment_method_id')->constrained()->nullOnDelete();
            $table->string('reference')->nullable()->after('method');
            $table->index(['company_id', 'payment_method_id', 'reference', 'amount'], 'payments_reconciliation_match_index');
        });

        Schema::table('bank_statements', function (Blueprint $table) {
            $table->foreignId('accounting_voucher_id')->nullable()->after('payment_method_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_statements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accounting_voucher_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_reconciliation_match_index');
            $table->dropConstrainedForeignId('accounting_voucher_id');
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn('reference');
        });
    }
};
