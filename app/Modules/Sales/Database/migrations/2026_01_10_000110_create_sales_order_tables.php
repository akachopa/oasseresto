<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('customer_address_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 60);
            $table->date('order_date');
            $table->date('requested_delivery_date')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('source', 20)->default('manual');
            $table->string('payment_term', 20)->default('cash');
            $table->boolean('is_tax_inclusive')->default(false);
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('shipping_cost', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->decimal('estimated_cost', 18, 4)->default(0);

            /*
             * Hasil pemeriksaan kredit dan margin disimpan pada dokumen supaya
             * alasan blokir atau approval tetap terbaca setelah kejadian
             * (PLAN 17 dan 18).
             */
            $table->string('credit_status', 20)->default('ok');
            $table->text('credit_note')->nullable();
            $table->boolean('requires_margin_approval')->default(false);
            $table->text('margin_note')->nullable();

            $table->text('note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('salesman_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'customer_id']);
        });

        Schema::create('sales_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('unit_id')->constrained('units');
            $table->foreignId('tax_code_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4);
            $table->decimal('list_price', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('discount_percent', 8, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4)->default(0);
            $table->decimal('delivered_base_quantity', 18, 4)->default(0);
            $table->decimal('invoiced_base_quantity', 18, 4)->default(0);
            $table->decimal('reserved_base_quantity', 18, 4)->default(0);
            $table->decimal('margin_percent', 8, 4)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['sales_order_id', 'product_id']);
        });

        DB::statement('ALTER TABLE sales_order_items ADD CONSTRAINT sales_order_items_quantity_check CHECK (quantity > 0 AND base_quantity > 0 AND delivered_base_quantity >= 0 AND invoiced_base_quantity >= 0 AND reserved_base_quantity >= 0)');
        DB::statement('ALTER TABLE sales_order_items ADD CONSTRAINT sales_order_items_price_check CHECK (unit_price >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
    }
};
