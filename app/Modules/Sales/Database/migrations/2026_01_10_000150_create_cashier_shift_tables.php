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
        Schema::create('cashier_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('number', 60);
            $table->string('status', 20)->default('open');
            $table->decimal('opening_cash', 18, 4)->default(0);
            $table->decimal('cash_sales', 18, 4)->default(0);
            $table->decimal('non_cash_sales', 18, 4)->default(0);
            $table->decimal('credit_sales', 18, 4)->default(0);
            $table->decimal('cash_in', 18, 4)->default(0);
            $table->decimal('cash_out', 18, 4)->default(0);
            $table->decimal('expected_cash', 18, 4)->default(0);
            $table->decimal('counted_cash', 18, 4)->nullable();
            $table->decimal('difference', 18, 4)->default(0);
            $table->unsignedInteger('transaction_count')->default(0);
            $table->text('note')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'user_id', 'status']);
        });

        /*
         * Satu kasir hanya boleh punya satu shift terbuka; tanpa ini, uang
         * kas tidak bisa dipertanggungjawabkan ke satu orang (PLAN 28).
         */
        DB::statement("CREATE UNIQUE INDEX cashier_shifts_open_unique ON cashier_shifts (company_id, user_id) WHERE status = 'open'");

        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->foreign('cashier_shift_id')->references('id')->on('cashier_shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropForeign(['cashier_shift_id']);
        });

        Schema::dropIfExists('cashier_shifts');
    }
};
