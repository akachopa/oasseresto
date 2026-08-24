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
        // Harga dasar per level harga, jadi acuan bila tidak ada price rule spesifik.
        Schema::create('product_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units');
            $table->decimal('price', 18, 4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'price_level_id', 'unit_id'], 'product_prices_unique');
        });

        DB::statement('ALTER TABLE product_prices ADD CONSTRAINT product_prices_positive CHECK (price >= 0)');

        /*
         * Price rule mendukung seluruh dimensi PLAN 15: level harga, grup
         * customer, customer spesifik, tier kuantitas, termin pembayaran,
         * cabang, periode, dan promosi. Kolom penentu yang null berarti
         * "berlaku untuk semua".
         */
        Schema::create('price_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 30);
            $table->unsignedSmallInteger('priority')->default(0);

            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('price_level_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units');

            $table->string('payment_term', 20)->nullable();
            $table->decimal('min_quantity', 18, 4)->default(0);
            $table->decimal('max_quantity', 18, 4)->nullable();

            $table->string('mode', 20)->default('fixed');
            $table->decimal('price', 18, 4)->nullable();
            $table->decimal('discount_percent', 8, 4)->nullable();
            $table->decimal('discount_amount', 18, 4)->nullable();

            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'product_id', 'is_active']);
            $table->index(['company_id', 'type']);
        });

        DB::statement("ALTER TABLE price_rules ADD CONSTRAINT price_rules_mode_check CHECK (mode IN ('fixed','discount_percent','discount_amount'))");
        DB::statement('ALTER TABLE price_rules ADD CONSTRAINT price_rules_quantity_check CHECK (min_quantity >= 0 AND (max_quantity IS NULL OR max_quantity >= min_quantity))');
    }

    public function down(): void
    {
        Schema::dropIfExists('price_rules');
        Schema::dropIfExists('product_prices');
    }
};
