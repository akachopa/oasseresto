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
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 60);
            $table->string('barcode', 60)->nullable();
            $table->string('name');
            $table->string('short_name', 100)->nullable();
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('base_unit_id')->constrained('units');
            $table->foreignId('purchase_unit_id')->nullable()->constrained('units');
            $table->foreignId('sales_unit_id')->nullable()->constrained('units');

            $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->foreignId('inventory_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('cogs_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('revenue_account_id')->nullable()->constrained('accounts')->nullOnDelete();

            $table->boolean('track_batch')->default(false);
            $table->boolean('track_expiry')->default(false);
            $table->boolean('track_serial')->default(false);
            $table->boolean('is_stocked')->default(true);

            $table->decimal('minimum_stock', 18, 4)->default(0);
            $table->decimal('maximum_stock', 18, 4)->default(0);
            $table->decimal('reorder_point', 18, 4)->default(0);
            $table->decimal('safety_stock', 18, 4)->default(0);
            $table->unsignedSmallInteger('lead_time_days')->default(0);

            $table->decimal('last_purchase_cost', 18, 4)->default(0);
            $table->decimal('average_cost', 18, 4)->default(0);
            $table->decimal('base_price', 18, 4)->default(0);
            $table->decimal('min_margin_percent', 8, 4)->nullable();

            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'product_category_id']);
            $table->index(['company_id', 'barcode']);
        });

        // Harga dan biaya tidak boleh negatif (PLAN 51).
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_non_negative_check CHECK (
            minimum_stock >= 0 AND maximum_stock >= 0 AND reorder_point >= 0
            AND safety_stock >= 0 AND average_cost >= 0 AND base_price >= 0
        )');

        Schema::create('product_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained();
            $table->decimal('conversion_to_base', 18, 6);
            $table->string('barcode', 60)->nullable();
            $table->boolean('is_base')->default(false);
            $table->boolean('allow_purchase')->default(true);
            $table->boolean('allow_sales')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'unit_id']);
        });

        DB::statement('ALTER TABLE product_units ADD CONSTRAINT product_units_conversion_positive CHECK (conversion_to_base > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('products');
    }
};
