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
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('inventory_account_id')->nullable()->after('min_stock')->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('income_account_id')->nullable()->after('inventory_account_id')->constrained('chart_of_accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_account_id');
            $table->dropConstrainedForeignId('income_account_id');
        });
    }
};
