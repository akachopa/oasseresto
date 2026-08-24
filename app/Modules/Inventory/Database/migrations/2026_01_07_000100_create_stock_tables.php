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
        /*
         * Batch adalah identitas lot per gudang. Menyimpan kuantitas di sini
         * membuat alokasi FEFO bisa dilakukan tanpa agregasi ledger.
         */
        Schema::create('batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number', 60);
            $table->date('received_date');
            $table->date('expiry_date')->nullable();
            $table->decimal('initial_quantity', 18, 4)->default(0);
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id', 'batch_number']);
            $table->index(['company_id', 'expiry_date']);
            $table->index(['company_id', 'product_id', 'warehouse_id']);
        });

        DB::statement('ALTER TABLE batches ADD CONSTRAINT batches_quantity_check CHECK (quantity >= 0 AND initial_quantity >= 0 AND unit_cost >= 0)');

        /*
         * Kartu stok. Baris di sini immutable: koreksi dilakukan dengan
         * gerakan baru, bukan mengubah baris lama (PLAN 24).
         */
        Schema::create('stock_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units');

            $table->string('transaction_type', 30);
            $table->date('transaction_date');
            $table->timestamp('transaction_at');

            $table->string('document_type', 40)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('document_number', 60)->nullable();

            $table->decimal('quantity', 18, 4);
            $table->decimal('base_quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('total_cost', 18, 4)->default(0);
            $table->decimal('balance_quantity', 18, 4)->default(0);
            $table->decimal('balance_value', 18, 4)->default(0);

            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'product_id', 'warehouse_id', 'transaction_at'], 'stock_ledgers_card_index');
            $table->index(['document_type', 'document_id']);
            $table->index(['company_id', 'transaction_date']);
        });

        DB::statement('ALTER TABLE stock_ledgers ADD CONSTRAINT stock_ledgers_movement_check CHECK (base_quantity <> 0)');

        /*
         * Saldo agregat per produk per gudang. Tidak dipasang constraint
         * non-negatif karena company boleh mengizinkan stok minus lewat flag
         * allow_negative_stock; penjagaannya ada di StockService.
         */
        Schema::create('stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->decimal('reserved_quantity', 18, 4)->default(0);
            $table->decimal('average_cost', 18, 4)->default(0);
            $table->decimal('total_value', 18, 4)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id', 'product_id']);
            $table->index(['company_id', 'product_id']);
        });

        DB::statement('ALTER TABLE stock_balances ADD CONSTRAINT stock_balances_reserved_check CHECK (reserved_quantity >= 0 AND average_cost >= 0)');

        /*
         * Layer biaya untuk FIFO. Satu baris per penerimaan; pengeluaran
         * mengurangi remaining_quantity dari layer tertua (PLAN 25).
         */
        Schema::create('stock_cost_layers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_ledger_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('received_at');
            $table->decimal('quantity', 18, 4);
            $table->decimal('remaining_quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4);
            $table->timestamps();

            $table->index(['company_id', 'product_id', 'warehouse_id', 'received_at'], 'stock_cost_layers_fifo_index');
        });

        DB::statement('ALTER TABLE stock_cost_layers ADD CONSTRAINT stock_cost_layers_quantity_check CHECK (
            quantity > 0 AND remaining_quantity >= 0 AND remaining_quantity <= quantity AND unit_cost >= 0
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_cost_layers');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('stock_ledgers');
        Schema::dropIfExists('batches');
    }
};
