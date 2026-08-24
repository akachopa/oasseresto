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
        Schema::create('purchase_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('purchase_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 60);
            $table->date('return_date');
            $table->string('reason', 30)->default('damaged');
            $table->string('settlement', 20)->default('credit_note');
            $table->string('status', 30)->default('draft');
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('purchase_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('unit_id')->constrained('units');
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tax_code_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4);
            $table->decimal('unit_price', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['purchase_return_id', 'product_id']);
        });

        DB::statement('ALTER TABLE purchase_return_items ADD CONSTRAINT purchase_return_items_quantity_check CHECK (quantity > 0 AND base_quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
    }
};
