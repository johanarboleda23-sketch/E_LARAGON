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
        Schema::table('factus_credentials', function (Blueprint $table) {
            $table->unsignedBigInteger('debit_note_numbering_range_id')->nullable()->after('credit_note_numbering_range_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('factus_credentials', function (Blueprint $table) {
            $table->dropColumn('debit_note_numbering_range_id');
        });
    }
};
