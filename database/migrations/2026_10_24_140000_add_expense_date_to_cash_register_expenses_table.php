<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cash_register_expenses', 'expense_date')) {
            Schema::table('cash_register_expenses', function (Blueprint $table) {
                $table->date('expense_date')->nullable();
            });
        }
    }

    /**
     * The column may predate this migration and contain existing expense dates.
     */
    public function down(): void
    {
    }
};
