<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('third_party_id')->constrained()->nullOnDelete();
            $table->string('withholding_concept')->default('none')->after('discount_total');
            $table->decimal('retention_base', 14, 2)->default(0)->after('withholding_concept');
            $table->decimal('retention_total', 14, 2)->default(0)->after('retention_base');
            $table->string('correction_concept_code', 5)->nullable()->after('dian_status');
            $table->string('dian_number')->nullable()->after('correction_concept_code');
            $table->string('dian_cude')->nullable()->after('dian_number');
            $table->json('dian_response')->nullable()->after('dian_cude');
            $table->timestamp('dian_sent_at')->nullable()->after('dian_response');
        });

        Schema::table('commercial_document_lines', function (Blueprint $table) {
            $table->decimal('quantity', 14, 4)->change();
        });

        Schema::table('factus_credentials', function (Blueprint $table) {
            $table->unsignedBigInteger('credit_note_numbering_range_id')->nullable()->after('invoice_numbering_range_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('commercial_document_lines')->whereRaw('quantity <> ROUND(quantity, 0)')->exists()) {
            throw new RuntimeException('Cannot restore integer quantities while fractional commercial document lines exist.');
        }

        Schema::table('commercial_documents', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn([
                'payment_method_id',
                'withholding_concept',
                'retention_base',
                'retention_total',
                'correction_concept_code',
                'dian_number',
                'dian_cude',
                'dian_response',
                'dian_sent_at',
            ]);
        });

        Schema::table('commercial_document_lines', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->change();
        });

        Schema::table('factus_credentials', function (Blueprint $table) {
            $table->dropColumn('credit_note_numbering_range_id');
        });
    }
};
