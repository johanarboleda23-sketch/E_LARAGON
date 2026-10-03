<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('factus_status')->nullable()->after('accounting_voucher_id');
            $table->string('factus_number')->nullable()->after('factus_status');
            $table->string('factus_cufe')->nullable()->after('factus_number');
            $table->json('factus_response')->nullable()->after('factus_cufe');
            $table->timestamp('factus_sent_at')->nullable()->after('factus_response');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['factus_status', 'factus_number', 'factus_cufe', 'factus_response', 'factus_sent_at']);
        });
    }
};
