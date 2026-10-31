<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('base_unit', 40)->nullable()->after('type');
            $table->decimal('stock', 14, 4)->default(0)->change();
            $table->decimal('min_stock', 14, 4)->default(0)->change();
        });
        DB::table('items')->where('type', 'producto')->whereNull('base_unit')->update(['base_unit' => 'unidad']);

        Schema::create('item_unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->decimal('factor', 14, 6);
            $table->decimal('sale_price', 14, 2)->nullable();
            $table->timestamps();
            $table->unique(['item_id', 'name']);
        });

        foreach ([
            'purchase_details' => 'item_id',
            'sale_details' => 'item_id',
            'commercial_document_lines' => 'item_id',
        ] as $tableName => $afterColumn) {
            Schema::table($tableName, function (Blueprint $table) use ($afterColumn): void {
                $table->foreignId('unit_conversion_id')->nullable()->after($afterColumn)->constrained('item_unit_conversions')->nullOnDelete();
                $table->string('unit_name', 80)->nullable();
                $table->decimal('unit_factor', 14, 6)->default(1);
                $table->decimal('quantity', 14, 4)->change();
            });
        }

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->decimal('quantity', 14, 4)->change();
            $table->decimal('stock_before', 14, 4)->change();
            $table->decimal('stock_after', 14, 4)->change();
        });

        if (Schema::hasTable('delivery_stop_returns')) {
            Schema::table('delivery_stop_returns', function (Blueprint $table): void {
                $table->decimal('quantity', 14, 4)->change();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'items' => ['stock', 'min_stock'],
            'purchase_details' => ['quantity'],
            'sale_details' => ['quantity'],
            'commercial_document_lines' => ['quantity'],
            'inventory_movements' => ['quantity', 'stock_before', 'stock_after'],
            'delivery_stop_returns' => ['quantity'],
        ] as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($tableName, $column)
                    && DB::table($tableName)->whereRaw("{$column} <> FLOOR({$column})")->exists()) {
                    throw new RuntimeException("No se puede revertir la precisión decimal: {$tableName}.{$column} contiene cantidades fraccionarias.");
                }
            }
        }

        Schema::table('purchase_details', function (Blueprint $table): void {
            $table->dropForeign(['unit_conversion_id']);
            $table->dropColumn(['unit_conversion_id', 'unit_name', 'unit_factor']);
            $table->integer('quantity')->change();
        });

        Schema::table('sale_details', function (Blueprint $table): void {
            $table->dropForeign(['unit_conversion_id']);
            $table->dropColumn(['unit_conversion_id', 'unit_name', 'unit_factor']);
            $table->integer('quantity')->change();
        });

        Schema::table('commercial_document_lines', function (Blueprint $table): void {
            $table->dropForeign(['unit_conversion_id']);
            $table->dropColumn(['unit_conversion_id', 'unit_name', 'unit_factor']);
            $table->unsignedInteger('quantity')->change();
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->integer('quantity')->change();
            $table->integer('stock_before')->change();
            $table->integer('stock_after')->change();
        });

        if (Schema::hasTable('delivery_stop_returns')) {
            Schema::table('delivery_stop_returns', function (Blueprint $table): void {
                $table->unsignedInteger('quantity')->change();
            });
        }

        Schema::dropIfExists('item_unit_conversions');
        Schema::table('items', function (Blueprint $table): void {
            $table->integer('stock')->default(0)->change();
            $table->integer('min_stock')->default(0)->change();
            $table->dropColumn('base_unit');
        });
    }
};
