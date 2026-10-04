<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_vouchers', function (Blueprint $table) {
            $table->dropUnique(['consecutive']);
            $table->unique(['company_id', 'consecutive']);
        });
    }

    public function down(): void
    {
        Schema::table('accounting_vouchers', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'consecutive']);
            $table->unique('consecutive');
        });
    }
};
