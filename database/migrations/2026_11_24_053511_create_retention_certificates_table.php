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
        if (! Schema::hasTable('retention_certificates')) {
            Schema::create('retention_certificates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('third_party_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedSmallInteger('taxable_year');
                $table->string('kind', 30);
                $table->string('third_party_name');
                $table->string('third_party_document')->nullable();
                $table->string('recipient_email')->nullable();
                $table->string('subject');
                $table->text('body');
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->json('account_breakdown')->nullable();
                $table->json('source_voucher_ids')->nullable();
                $table->string('status', 20)->default('draft');
                $table->timestamp('sent_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

            });
        }

        if (! Schema::hasColumns('retention_certificates', ['company_id', 'taxable_year', 'kind', 'third_party_id'])) {
            throw new RuntimeException('La tabla de certificados existente no tiene las columnas esperadas.');
        }

        if (! Schema::hasIndex('retention_certificates', ['company_id', 'taxable_year', 'kind', 'third_party_id'], 'unique')) {
            Schema::table('retention_certificates', function (Blueprint $table): void {
                $table->unique(['company_id', 'taxable_year', 'kind', 'third_party_id'], 'ret_cert_company_year_party_unique');
            });
        }

        if (! Schema::hasIndex('retention_certificates', 'ret_cert_company_year_kind_idx')) {
            Schema::table('retention_certificates', function (Blueprint $table): void {
                $table->index(['company_id', 'taxable_year', 'kind'], 'ret_cert_company_year_kind_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retention_certificates');
    }
};
