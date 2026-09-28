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
            $table->decimal('latitude', 10, 7)->nullable()->after('city');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('duration_seconds')->nullable()->after('longitude');
            $table->unsignedInteger('distance_meters')->nullable()->after('duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_stops', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'duration_seconds', 'distance_meters']);
        });
    }
};
