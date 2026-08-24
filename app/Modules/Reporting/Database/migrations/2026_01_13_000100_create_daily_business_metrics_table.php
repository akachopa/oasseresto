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
        Schema::create('daily_business_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('metric_date');

            $table->decimal('sales_amount', 18, 4)->default(0);
            $table->unsignedInteger('sales_count')->default(0);
            $table->decimal('cogs_amount', 18, 4)->default(0);
            $table->decimal('gross_profit', 18, 4)->default(0);
            $table->decimal('sales_return_amount', 18, 4)->default(0);
            $table->decimal('receipt_amount', 18, 4)->default(0);
            $table->decimal('payment_amount', 18, 4)->default(0);
            $table->decimal('expense_amount', 18, 4)->default(0);

            $table->decimal('cash_balance', 18, 4)->default(0);
            $table->decimal('ar_outstanding', 18, 4)->default(0);
            $table->decimal('ar_overdue', 18, 4)->default(0);
            $table->decimal('ap_outstanding', 18, 4)->default(0);
            $table->decimal('ap_overdue', 18, 4)->default(0);
            $table->decimal('inventory_value', 18, 4)->default(0);

            $table->unsignedInteger('below_reorder_count')->default(0);
            $table->unsignedInteger('pending_approval_count')->default(0);
            $table->unsignedInteger('pending_picking_count')->default(0);
            $table->unsignedInteger('open_po_count')->default(0);

            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'metric_date']);
        });

        /*
         * PostgreSQL memperlakukan NULL sebagai nilai berbeda di unique index,
         * jadi baris company-wide (branch_id null) memakai COALESCE.
         */
        DB::statement('CREATE UNIQUE INDEX daily_business_metrics_scope_date ON daily_business_metrics (company_id, COALESCE(branch_id, 0), metric_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_business_metrics');
    }
};
