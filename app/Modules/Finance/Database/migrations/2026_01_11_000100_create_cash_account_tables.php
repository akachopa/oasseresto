<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kas dan bank disatukan dalam satu tabel dengan kolom type. Keduanya
     * berperilaku sama (saldo, mutasi, rekonsiliasi); memisahkan tabel hanya
     * akan menduplikasi seluruh logika mutasi kas.
     */
    public function up(): void
    {
        Schema::create('cash_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('type', 20)->default('cash');
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 60)->nullable();
            $table->string('account_holder', 100)->nullable();
            $table->decimal('opening_balance', 18, 4)->default(0);
            $table->decimal('balance', 18, 4)->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'type']);
        });

        Schema::create('cash_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_account_id')->constrained();
            $table->date('transaction_date');
            $table->string('direction', 10);
            $table->string('category', 30)->default('other');
            $table->string('document_type', 50)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('document_number', 60)->nullable();
            $table->decimal('amount', 18, 4);
            $table->decimal('balance_after', 18, 4)->default(0);
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'cash_account_id', 'transaction_date']);
            $table->index(['company_id', 'document_type', 'document_id']);
        });

        DB::statement('ALTER TABLE cash_transactions ADD CONSTRAINT cash_transactions_amount_check CHECK (amount > 0)');
        DB::statement("ALTER TABLE cash_transactions ADD CONSTRAINT cash_transactions_direction_check CHECK (direction IN ('in', 'out'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
        Schema::dropIfExists('cash_accounts');
    }
};
