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
        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('skip_dian')->default(false)->after('factus_status');
        });
        Schema::table('support_documents', function (Blueprint $table) {
            $table->boolean('skip_dian')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('skip_dian');
        });
        Schema::table('support_documents', function (Blueprint $table) {
            $table->dropColumn('skip_dian');
        });
    }
};
