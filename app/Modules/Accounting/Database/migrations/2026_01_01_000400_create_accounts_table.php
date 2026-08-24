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
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('type', 30);
            $table->string('subtype', 40)->nullable();
            $table->string('slug', 60)->nullable();
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'type']);
        });

        DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_type_check CHECK (type IN ('asset','liability','equity','revenue','cogs','expense','other_income','other_expense'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
