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
        if (Schema::hasColumn('cash_register_expenses', 'expense_date')) {
            return;
        }

        Schema::table('cash_register_expenses', function (Blueprint $table) {
            $table->date('expense_date')->nullable()->after('concept');
        });

        DB::table('cash_register_expenses')
            ->whereNull('expense_date')
            ->update(['expense_date' => DB::raw('COALESCE(DATE(created_at), CURRENT_DATE)')]);

        Schema::table('cash_register_expenses', function (Blueprint $table) {
            $table->date('expense_date')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The column already exists in some environments, so it is not safe to drop on rollback.
    }
};
