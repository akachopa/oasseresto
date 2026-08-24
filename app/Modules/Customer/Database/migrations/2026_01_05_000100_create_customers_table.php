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
        Schema::create('customer_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->foreignId('price_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('default_payment_term', 20)->default('cash');
            $table->decimal('default_credit_limit', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('price_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('salesman_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();

            $table->string('code', 30);
            $table->string('name');
            $table->string('type', 20)->default('retail');
            $table->string('pic_name', 100)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('sales_area', 100)->nullable();
            $table->string('tax_number', 40)->nullable();

            $table->string('payment_term', 20)->default('cash');
            $table->decimal('credit_limit', 18, 2)->default(0);
            $table->decimal('outstanding_amount', 18, 2)->default(0);
            $table->decimal('overdue_amount', 18, 2)->default(0);

            $table->string('preferred_delivery', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'salesman_id']);
        });

        DB::statement('ALTER TABLE customers ADD CONSTRAINT customers_credit_check CHECK (credit_limit >= 0 AND outstanding_amount >= 0)');

        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 60);
            $table->string('pic_name', 100)->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address');
            $table->string('city', 100)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        /*
         * Ringkasan perilaku customer (PLAN 16). Diperbarui oleh scheduler dan
         * event pembayaran, bukan dihitung ulang setiap kali dashboard dibuka.
         */
        Schema::create('customer_credit_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('last_order_date')->nullable();
            $table->date('last_payment_date')->nullable();
            $table->unsignedInteger('order_count')->default(0);
            $table->decimal('total_purchase', 18, 2)->default(0);
            $table->decimal('average_invoice', 18, 2)->default(0);
            $table->decimal('average_days_to_pay', 8, 2)->default(0);
            $table->unsignedInteger('overdue_count')->default(0);
            $table->decimal('max_overdue_days', 8, 2)->default(0);
            $table->string('payment_behavior', 20)->default('unknown');
            $table->boolean('is_dormant')->default(false);
            $table->timestamps();

            $table->unique('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credit_profiles');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('customer_groups');
    }
};
