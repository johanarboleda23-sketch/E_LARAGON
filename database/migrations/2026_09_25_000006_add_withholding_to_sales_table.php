<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('withholding_concept')->default('none')->after('discount_total');
            $table->decimal('retention_base', 12, 2)->default(0)->after('withholding_concept');
            $table->decimal('retention_total', 12, 2)->default(0)->after('retention_base');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['withholding_concept', 'retention_base', 'retention_total']);
        });
    }
};
