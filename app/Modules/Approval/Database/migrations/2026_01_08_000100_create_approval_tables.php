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
        Schema::create('approval_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type', 50);
            $table->string('name', 120);
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->decimal('min_amount', 18, 4)->default(0);
            $table->decimal('max_amount', 18, 4)->nullable();
            $table->string('trigger', 40)->default('always');
            $table->string('approver_role', 60)->nullable();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'document_type', 'is_active']);
        });

        // Satu langkah harus punya penentu approver, entah role atau user.
        DB::statement('ALTER TABLE approval_rules ADD CONSTRAINT approval_rules_approver_check CHECK (approver_role IS NOT NULL OR approver_user_id IS NOT NULL)');
        DB::statement('ALTER TABLE approval_rules ADD CONSTRAINT approval_rules_amount_check CHECK (max_amount IS NULL OR max_amount >= min_amount)');

        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type', 50);
            $table->unsignedBigInteger('document_id');
            $table->string('document_number', 60)->nullable();
            $table->string('title', 180);
            $table->decimal('amount', 18, 4)->default(0);
            $table->string('status', 30)->default('pending');
            $table->string('trigger', 40)->default('always');
            $table->text('reason')->nullable();
            $table->unsignedSmallInteger('current_step')->default(1);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['document_type', 'document_id']);
        });

        Schema::create('approval_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('approver_role', 60)->nullable();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['approval_request_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_rules');
    }
};
