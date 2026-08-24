<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('pic_name', 100)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('tax_number', 40)->nullable();
            $table->string('bank_name', 60)->nullable();
            $table->string('bank_account', 60)->nullable();
            $table->string('bank_account_name', 100)->nullable();

            $table->string('payment_term', 20)->default('net_30');
            $table->unsignedSmallInteger('lead_time_days')->default(3);
            $table->decimal('minimum_order_amount', 18, 2)->default(0);
            $table->decimal('outstanding_amount', 18, 2)->default(0);

            $table->decimal('on_time_rate', 8, 4)->default(0);
            $table->decimal('quality_rate', 8, 4)->default(0);
            $table->decimal('average_lead_time', 8, 2)->default(0);
            $table->unsignedInteger('order_count')->default(0);
            $table->decimal('total_purchase', 18, 2)->default(0);

            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('supplier_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units');
            $table->string('supplier_sku', 60)->nullable();
            $table->decimal('last_price', 18, 4)->default(0);
            $table->decimal('minimum_quantity', 18, 4)->default(0);
            $table->unsignedSmallInteger('lead_time_days')->default(0);
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();

            $table->unique(['supplier_id', 'product_id']);
        });

        Schema::create('supplier_price_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units');
            $table->date('effective_date');
            $table->decimal('price', 18, 4);
            $table->string('source', 30)->default('purchase_order');
            $table->string('reference_number', 60)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'product_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_price_history');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('suppliers');
    }
};
