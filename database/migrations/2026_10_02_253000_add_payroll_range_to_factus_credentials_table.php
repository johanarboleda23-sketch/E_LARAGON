<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factus_credentials', function (Blueprint $table) {
            $table->unsignedBigInteger('payroll_numbering_range_id')->nullable()->after('invoice_numbering_range_id');
        });
    }

    public function down(): void
    {
        Schema::table('factus_credentials', function (Blueprint $table) {
            $table->dropColumn('payroll_numbering_range_id');
        });
    }
};
