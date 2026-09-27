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
        Schema::create('commercial_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('third_party_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('converted_purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->string('document_type', 32);
            $table->string('consecutive', 60);
            $table->string('status', 20)->default('draft');
            $table->string('third_party_name');
            $table->string('third_party_document')->nullable();
            $table->string('recipient_email')->nullable();
            $table->date('document_date');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('iva_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'document_type', 'consecutive']);
            $table->index(['company_id', 'document_type', 'created_at']);
        });

        Schema::create('commercial_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('iva_percentage', 5, 2)->default(0);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('utility_percentage', 5, 2)->default(0);
            $table->decimal('line_subtotal', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('iva_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commercial_document_lines');
        Schema::dropIfExists('commercial_documents');
    }
};
