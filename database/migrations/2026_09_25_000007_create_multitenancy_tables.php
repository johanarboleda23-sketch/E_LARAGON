<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tax_id')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('company_user', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('admin');
            $table->timestamps();
            $table->primary(['company_id', 'user_id']);
        });

        $companyId = DB::table('companies')->insertGetId([
            'name' => 'Empresa principal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (DB::table('users')->pluck('id') as $userId) {
            DB::table('company_user')->insert([
                'company_id' => $companyId,
                'user_id' => $userId,
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $tenantTables = [
            'items', 'purchases', 'purchase_details', 'inventory_movements',
            'payment_methods', 'account_payables', 'purchase_adjustments',
            'document_types', 'chart_of_accounts', 'accounting_vouchers',
            'accounting_voucher_lines', 'sales', 'sale_details',
        ];

        foreach ($tenantTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
            });
            DB::table($tableName)->update(['company_id' => $companyId]);
        }
    }

    public function down(): void
    {
        $tenantTables = [
            'items', 'purchases', 'purchase_details', 'inventory_movements',
            'payment_methods', 'account_payables', 'purchase_adjustments',
            'document_types', 'chart_of_accounts', 'accounting_vouchers',
            'accounting_voucher_lines', 'sales', 'sale_details',
        ];

        foreach (array_reverse($tenantTables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        Schema::dropIfExists('company_user');
        Schema::dropIfExists('companies');
    }
};
