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
        Schema::table('cash_register_sessions', function (Blueprint $table) {
            $table->json('denominations')->nullable()->after('counted_cash');
            $table->decimal('movements_cash', 14, 2)->default(0)->after('cash_out');
            $table->decimal('movements_bank', 14, 2)->default(0)->after('movements_cash');
            $table->decimal('movements_credit', 14, 2)->default(0)->after('movements_bank');
            $table->decimal('expenses_total', 14, 2)->default(0)->after('movements_credit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_register_sessions', function (Blueprint $table) {
            $table->dropColumn(['denominations', 'movements_cash', 'movements_bank', 'movements_credit', 'expenses_total']);
        });
    }
};
