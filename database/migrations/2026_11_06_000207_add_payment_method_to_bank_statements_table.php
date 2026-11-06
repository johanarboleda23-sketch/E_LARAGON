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
        Schema::table('bank_statements', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->index(['company_id', 'payment_method_id', 'transaction_date'], 'bank_statements_account_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_statements', function (Blueprint $table) {
            $table->dropIndex('bank_statements_account_date_index');
            $table->dropConstrainedForeignId('payment_method_id');
        });
    }
};
