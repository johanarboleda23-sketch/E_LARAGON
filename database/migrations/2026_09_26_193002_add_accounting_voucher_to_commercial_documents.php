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
        Schema::table('commercial_documents', function (Blueprint $table) {
            $table->timestamp('accounted_at')->nullable()->after('converted_at');
        });

        Schema::table('accounting_vouchers', function (Blueprint $table) {
            $table->foreignId('commercial_document_id')
                ->nullable()
                ->after('created_by')
                ->unique()
                ->constrained('commercial_documents')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table) {
            $table->dropColumn('accounted_at');
        });

        Schema::table('accounting_vouchers', function (Blueprint $table) {
            $table->dropForeign(['commercial_document_id']);
            $table->dropUnique(['commercial_document_id']);
            $table->dropColumn('commercial_document_id');
        });
    }
};
