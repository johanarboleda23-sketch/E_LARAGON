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
        Schema::table('delivery_stops', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('third_party_id')->constrained('sales')->nullOnDelete();
            $table->string('payment_action')->nullable()->after('status');
            $table->decimal('payment_amount', 14, 2)->nullable()->after('payment_action');
            $table->timestamp('payment_recorded_at')->nullable()->after('payment_amount');
            $table->date('reschedule_date')->nullable()->after('payment_recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_stops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sale_id');
            $table->dropColumn(['payment_action', 'payment_amount', 'payment_recorded_at', 'reschedule_date']);
        });
    }
};
