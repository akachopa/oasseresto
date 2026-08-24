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
        Schema::create('receivables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('salesman_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type', 50);
            $table->unsignedBigInteger('document_id');
            $table->string('document_number', 60);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('amount', 18, 4);
            $table->decimal('paid_amount', 18, 4)->default(0);
            $table->decimal('outstanding_amount', 18, 4)->default(0);
            $table->string('status', 30)->default('open');
            $table->text('note')->nullable();
            $table->timestamps();

            // Satu dokumen sumber hanya boleh menghasilkan satu baris piutang.
            $table->unique(['company_id', 'document_type', 'document_id'], 'receivables_document_unique');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'due_date']);
        });

        DB::statement('ALTER TABLE receivables ADD CONSTRAINT receivables_amount_check CHECK (paid_amount >= 0 AND outstanding_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('receivables');
    }
};
