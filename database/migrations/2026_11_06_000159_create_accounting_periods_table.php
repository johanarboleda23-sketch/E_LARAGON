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
        if (Schema::hasTable('accounting_periods')) {
            $indexes = collect(Schema::getIndexes('accounting_periods'))->pluck('name');

            if (! $indexes->contains('accounting_periods_company_status_dates_idx')) {
                Schema::table('accounting_periods', function (Blueprint $table) {
                    $table->index(
                        ['company_id', 'status', 'period_start', 'period_end'],
                        'accounting_periods_company_status_dates_idx',
                    );
                });
            }

            return;
        }

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('period_type', 10);
            $table->string('period_key', 7);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 10)->default('open');
            $table->json('locked_modules');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'period_type', 'period_key']);
            $table->index(
                ['company_id', 'status', 'period_start', 'period_end'],
                'accounting_periods_company_status_dates_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
