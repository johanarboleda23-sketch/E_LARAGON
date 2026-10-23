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
            if (! Schema::hasColumn('delivery_stops', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('delivery_route_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('delivery_stops', 'sale_id')) {
                $table->foreignId('sale_id')->nullable()->unique()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('delivery_stops', 'payment_action')) {
                $table->string('payment_action')->nullable();
            }
            if (! Schema::hasColumn('delivery_stops', 'collected_amount')) {
                $table->decimal('collected_amount', 18, 2)->default(0);
            }
            if (! Schema::hasColumn('delivery_stops', 'payment_method')) {
                $table->string('payment_method')->nullable();
            }
        });
        if (Schema::hasColumn('delivery_stops', 'company_id')) {
            DB::table('delivery_stops')->whereNull('company_id')->update([
                'company_id' => DB::raw('(select company_id from delivery_routes where delivery_routes.id = delivery_stops.delivery_route_id)'),
            ]);
        }

        Schema::table('cash_register_sessions', function (Blueprint $table) {
            foreach ([
                'bill_100000', 'bill_50000', 'bill_20000', 'bill_10000', 'bill_5000',
                'bill_2000', 'bill_1000', 'coin_1000', 'coin_500', 'coin_200', 'coin_100', 'coin_50',
            ] as $denomination) {
                if (! Schema::hasColumn('cash_register_sessions', $denomination)) {
                    $table->unsignedInteger($denomination)->default(0);
                }
            }
        });

        Schema::table('cash_register_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_register_movements', 'company_id')) {
                $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('cash_register_movements', 'cash_register_session_id')) {
                $table->foreignId('cash_register_session_id')->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('cash_register_movements', 'delivery_stop_id')) {
                $table->foreignId('delivery_stop_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('cash_register_movements', 'sale_id')) {
                $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('cash_register_movements', 'payment_id')) {
                $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('cash_register_movements', 'accounting_voucher_id')) {
                $table->foreignId('accounting_voucher_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('cash_register_movements', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('cash_register_movements', 'movement_type')) {
                $table->string('movement_type', 40);
            }
            if (! Schema::hasColumn('cash_register_movements', 'payment_method')) {
                $table->string('payment_method', 40)->nullable();
            }
            if (! Schema::hasColumn('cash_register_movements', 'amount')) {
                $table->decimal('amount', 18, 2);
            }
            if (! Schema::hasColumn('cash_register_movements', 'notes')) {
                $table->text('notes')->nullable();
            }
        });

        Schema::table('cash_register_expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_register_expenses', 'company_id')) {
                $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('cash_register_expenses', 'cash_register_session_id')) {
                $table->foreignId('cash_register_session_id')->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('cash_register_expenses', 'accounting_voucher_id')) {
                $table->foreignId('accounting_voucher_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('cash_register_expenses', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('cash_register_expenses', 'category')) {
                $table->string('category', 80)->default('otro');
            }
            if (! Schema::hasColumn('cash_register_expenses', 'concept')) {
                $table->string('concept');
            }
            if (! Schema::hasColumn('cash_register_expenses', 'amount')) {
                $table->decimal('amount', 18, 2);
            }
        });

        if (! Schema::hasTable('delivery_stop_returns')) {
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
    }

    /**
     * This migration reconciles pre-existing schema and cannot safely identify which columns it owns.
     */
    public function down(): void
    {
    }
};
