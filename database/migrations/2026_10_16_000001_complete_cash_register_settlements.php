<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_stops', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('delivery_route_id')->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('payment_action')->nullable();
            $table->decimal('collected_amount', 18, 2)->default(0);
            $table->string('payment_method')->nullable();
        });
        DB::table('delivery_stops')->whereNull('company_id')->update([
            'company_id' => DB::raw('(select company_id from delivery_routes where delivery_routes.id = delivery_stops.delivery_route_id)'),
        ]);

        Schema::table('cash_register_sessions', function (Blueprint $table) {
            $table->unsignedInteger('bill_100000')->default(0);
            $table->unsignedInteger('bill_50000')->default(0);
            $table->unsignedInteger('bill_20000')->default(0);
            $table->unsignedInteger('bill_10000')->default(0);
            $table->unsignedInteger('bill_5000')->default(0);
            $table->unsignedInteger('bill_2000')->default(0);
            $table->unsignedInteger('bill_1000')->default(0);
            $table->unsignedInteger('coin_1000')->default(0);
            $table->unsignedInteger('coin_500')->default(0);
            $table->unsignedInteger('coin_200')->default(0);
            $table->unsignedInteger('coin_100')->default(0);
            $table->unsignedInteger('coin_50')->default(0);
        });

        Schema::table('cash_register_movements', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_register_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_stop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('accounting_voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('movement_type', 40);
            $table->string('payment_method', 40)->nullable();
            $table->decimal('amount', 18, 2);
            $table->text('notes')->nullable();
        });

        Schema::table('cash_register_expenses', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_register_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 80)->default('otro');
            $table->string('concept');
            $table->decimal('amount', 18, 2);
        });

        Schema::create('delivery_stop_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_stop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_detail_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->index(['delivery_stop_id', 'sale_detail_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_stop_returns');

        Schema::table('cash_register_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_register_session_id');
            $table->dropConstrainedForeignId('accounting_voucher_id');
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['category', 'concept', 'amount']);
        });

        Schema::table('cash_register_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_register_session_id');
            $table->dropConstrainedForeignId('delivery_stop_id');
            $table->dropConstrainedForeignId('sale_id');
            $table->dropConstrainedForeignId('payment_id');
            $table->dropConstrainedForeignId('accounting_voucher_id');
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['movement_type', 'payment_method', 'amount', 'notes']);
        });

        Schema::table('cash_register_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'bill_100000', 'bill_50000', 'bill_20000', 'bill_10000', 'bill_5000',
                'bill_2000', 'bill_1000', 'coin_1000', 'coin_500', 'coin_200', 'coin_100', 'coin_50',
            ]);
        });

        Schema::table('delivery_stops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sale_id');
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['payment_action', 'collected_amount', 'payment_method']);
        });
    }
};
