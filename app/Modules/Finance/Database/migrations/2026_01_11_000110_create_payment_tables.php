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
        Schema::create('receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('cash_account_id')->constrained();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 60);
            $table->date('receipt_date');
            $table->string('status', 30)->default('draft');
            $table->string('method', 20)->default('cash');
            $table->string('reference', 100)->nullable();
            $table->date('cleared_date')->nullable();
            $table->decimal('amount', 18, 4);
            $table->decimal('allocated_amount', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'customer_id']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('cash_account_id')->constrained();
            $table->string('number', 60);
            $table->date('payment_date');
            $table->string('status', 30)->default('draft');
            $table->string('method', 20)->default('transfer');
            $table->string('reference', 100)->nullable();
            $table->decimal('amount', 18, 4);
            $table->decimal('allocated_amount', 18, 4)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'supplier_id']);
        });

        /*
         * Satu tabel alokasi dipakai penerimaan maupun pembayaran, sehingga
         * aturan "alokasi tidak boleh melebihi sisa dokumen" hanya ada di satu
         * tempat (PLAN 32).
         */
        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('payment_type', 20);
            $table->unsignedBigInteger('payment_id');
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->decimal('amount', 18, 4);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'payment_type', 'payment_id']);
            $table->index(['company_id', 'target_type', 'target_id']);
        });

        DB::statement('ALTER TABLE receipts ADD CONSTRAINT receipts_amount_check CHECK (amount > 0 AND allocated_amount >= 0 AND discount_amount >= 0)');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0 AND allocated_amount >= 0 AND discount_amount >= 0)');
        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_amount_check CHECK (amount > 0 AND discount_amount >= 0)');
        DB::statement("ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_type_check CHECK (payment_type IN ('receipt', 'payment') AND target_type IN ('receivable', 'payable'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('receipts');
    }
};
