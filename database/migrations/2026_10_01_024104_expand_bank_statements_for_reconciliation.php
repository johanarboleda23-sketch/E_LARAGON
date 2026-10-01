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
            $table->foreignId('company_id')->after('id')->constrained()->cascadeOnDelete();
            $table->date('transaction_date')->after('company_id');
            $table->string('reference')->nullable()->after('transaction_date');
            $table->text('description')->nullable()->after('reference');
            $table->string('transaction_type', 20)->after('description');
            $table->decimal('amount', 15, 2)->after('transaction_type');
            $table->string('status', 20)->default('pending')->after('amount');
            $table->string('matched_type')->nullable()->after('status');
            $table->unsignedBigInteger('matched_id')->nullable()->after('matched_type');
            $table->timestamp('reconciled_at')->nullable()->after('matched_id');
            $table->foreignId('reconciled_by')->nullable()->after('reconciled_at')->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable()->after('reconciled_by');
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();

            $table->index(['company_id', 'transaction_date']);
            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_statements', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['reconciled_by']);
            $table->dropForeign(['created_by']);
            $table->dropIndex(['bank_statements_company_id_transaction_date_index']);
            $table->dropIndex(['bank_statements_company_id_status_index']);
            $table->dropColumn([
                'company_id', 'transaction_date', 'reference', 'description', 'transaction_type',
                'amount', 'status', 'matched_type', 'matched_id', 'reconciled_at',
                'reconciled_by', 'notes', 'created_by',
            ]);
        });
    }
};
