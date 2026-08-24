<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 60);
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('period', 6);
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'document_type', 'branch_id', 'period'], 'document_sequences_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
