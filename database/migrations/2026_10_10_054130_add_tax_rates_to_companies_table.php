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
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('ica_rate_per_thousand', 8, 2)->nullable()->after('social_security_operator_url');
            $table->decimal('income_tax_rate_percentage', 5, 2)->nullable()->after('ica_rate_per_thousand');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['ica_rate_per_thousand', 'income_tax_rate_percentage']);
        });
    }
};
