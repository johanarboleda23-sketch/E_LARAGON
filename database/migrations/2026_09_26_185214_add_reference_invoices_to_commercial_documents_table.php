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
            $table->foreignId('reference_sale_id')->nullable()->after('third_party_id')->constrained('sales')->nullOnDelete();
            $table->foreignId('reference_purchase_id')->nullable()->after('reference_sale_id')->constrained('purchases')->nullOnDelete();
            $table->string('dian_status', 24)->default('not_configured')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table) {
            $table->dropForeign(['reference_sale_id']);
            $table->dropForeign(['reference_purchase_id']);
            $table->dropColumn(['reference_sale_id', 'reference_purchase_id', 'dian_status']);
        });
    }
};
