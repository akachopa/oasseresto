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
        Schema::create('purchase_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('number', 60);
            $table->date('request_date');
            $table->date('needed_date')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('priority', 20)->default('normal');
            $table->decimal('estimated_total', 18, 4)->default(0);
            $table->text('note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('purchase_request_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('unit_id')->constrained('units');
            $table->foreignId('suggested_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4);
            $table->decimal('ordered_base_quantity', 18, 4)->default(0);
            $table->decimal('estimated_price', 18, 4)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['purchase_request_id', 'product_id']);
        });

        DB::statement('ALTER TABLE purchase_request_items ADD CONSTRAINT purchase_request_items_quantity_check CHECK (quantity > 0 AND base_quantity > 0 AND ordered_base_quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
    }
};
