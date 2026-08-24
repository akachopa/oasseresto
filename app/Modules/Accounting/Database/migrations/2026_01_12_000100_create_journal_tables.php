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
        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('accounting_period_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 60);
            $table->date('entry_date');
            $table->string('status', 20)->default('posted');
            $table->string('source', 30)->default('system');

            /*
             * document_type + document_id + purpose adalah kunci idempotensi:
             * satu dokumen bisnis hanya boleh menghasilkan satu jurnal per
             * tujuan, berapa kali pun event-nya diproses (PLAN 66).
             */
            $table->string('document_type', 50)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('document_number', 60)->nullable();
            $table->string('purpose', 50)->nullable();

            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->decimal('total_debit', 18, 4)->default(0);
            $table->decimal('total_credit', 18, 4)->default(0);
            $table->string('description');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->unique(
                ['company_id', 'document_type', 'document_id', 'purpose'],
                'journal_entries_document_unique',
            );
            $table->index(['company_id', 'entry_date']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->decimal('debit', 18, 4)->default(0);
            $table->decimal('credit', 18, 4)->default(0);
            $table->string('memo')->nullable();
            $table->string('partner_type', 20)->nullable();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'account_id']);
            $table->index(['journal_entry_id', 'sequence']);
        });

        // Satu baris tidak boleh debit dan kredit sekaligus, dan tidak boleh nol.
        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_side_check CHECK (
            debit >= 0 AND credit >= 0 AND (debit = 0 OR credit = 0) AND (debit + credit) > 0
        )');

        // Jurnal wajib balance; ini benteng terakhir kalau ada bug di service.
        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_balance_check CHECK (total_debit = total_credit)');
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
    }
};
