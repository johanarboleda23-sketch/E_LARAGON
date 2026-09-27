<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('chart_of_accounts')->nullOnDelete();
            $table->string('account_type', 20)->default('auxiliar')->after('nature');
            $table->boolean('has_due_date')->default(false)->after('allows_posting');
        });

        Schema::table('accounting_vouchers', function (Blueprint $table) {
            $table->foreignId('deleted_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_vouchers', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['deleted_by', 'deleted_at']);
        });
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'account_type', 'has_due_date']);
        });
    }
};
