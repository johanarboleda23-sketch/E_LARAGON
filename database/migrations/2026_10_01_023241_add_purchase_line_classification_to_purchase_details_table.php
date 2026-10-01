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
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->string('purchase_line_type', 20)->default('producto')->after('purchase_id');
            $table->foreignId('item_id')->nullable()->change();
            $table->foreignId('chart_of_account_id')->nullable()->after('item_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('line_description')->nullable()->after('chart_of_account_id');
            $table->unsignedInteger('useful_life_months')->nullable()->after('line_description');
            $table->string('depreciation_method', 20)->nullable()->after('useful_life_months');
            $table->decimal('residual_value', 12, 2)->nullable()->after('depreciation_method');
            $table->foreignId('depreciation_expense_account_id')->nullable()->after('residual_value')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('accumulated_depreciation_account_id')->nullable()->after('depreciation_expense_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->dropForeign(['chart_of_account_id']);
            $table->dropForeign(['depreciation_expense_account_id']);
            $table->dropForeign(['accumulated_depreciation_account_id']);
            $table->dropColumn([
                'purchase_line_type',
                'chart_of_account_id',
                'line_description',
                'useful_life_months',
                'depreciation_method',
                'residual_value',
                'depreciation_expense_account_id',
                'accumulated_depreciation_account_id',
            ]);
            $table->foreignId('item_id')->constrained()->onDelete('cascade')->change();
        });
    }
};
