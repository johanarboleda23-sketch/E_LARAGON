<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('third_parties', function (Blueprint $table) {
            $table->string('address')->nullable();
            $table->string('city', 150)->nullable();
            $table->string('neighborhood', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('third_parties', function (Blueprint $table) {
            $table->dropColumn(['address', 'city', 'neighborhood']);
        });
    }
};
