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
        if (Schema::hasTable('accounting_period_events')) {
            $indexes = collect(Schema::getIndexes('accounting_period_events'))->pluck('name');

            if (! $indexes->contains('accounting_period_events_company_period_created_idx')) {
                Schema::table('accounting_period_events', function (Blueprint $table) {
                    $table->index(
                        ['company_id', 'accounting_period_id', 'created_at'],
                        'accounting_period_events_company_period_created_idx',
                    );
                });
            }

            return;
        }

        Schema::create('accounting_period_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_period_id')->constrained()->cascadeOnDelete();
            $table->string('action', 10);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(
                ['company_id', 'accounting_period_id', 'created_at'],
                'accounting_period_events_company_period_created_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_period_events');
    }
};
