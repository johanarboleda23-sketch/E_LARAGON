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
        Schema::create('opening_balance_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_type', 20);
            $table->foreignId('third_party_id')->nullable()->constrained()->nullOnDelete();
            $table->string('third_party_name');
            $table->string('third_party_document', 100);
            $table->string('invoice_number', 100);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('cufe', 180)->nullable();
            $table->decimal('opening_balance', 18, 2);
            $table->foreignId('accounting_voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('accounting_voucher_line_id')->nullable()->constrained('accounting_voucher_lines')->nullOnDelete();
            $table->timestamps();

            $table->unique('accounting_voucher_line_id', 'opening_balance_voucher_line_unique');
            $table->unique(['company_id', 'invoice_type', 'third_party_id', 'invoice_number'], 'opening_balance_invoice_number_unique');
            $table->unique(['company_id', 'cufe'], 'opening_balance_cufe_unique');
            $table->index(['company_id', 'invoice_type', 'third_party_id', 'due_date'], 'opening_balance_party_due_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_balance_invoices');
    }
};
