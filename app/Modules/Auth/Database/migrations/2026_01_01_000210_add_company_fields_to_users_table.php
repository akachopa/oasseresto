<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('default_branch_id')->nullable()->after('company_id')
                ->constrained('branches')->nullOnDelete();
            $table->string('username', 60)->nullable()->after('name');
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('job_title', 100)->nullable()->after('phone');
            $table->string('avatar_path')->nullable()->after('job_title');
            $table->boolean('is_active')->default(true)->after('avatar_path');
            $table->timestamp('last_login_at')->nullable();
            $table->softDeletes();

            $table->unique(['company_id', 'username']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'username']);
            $table->dropIndex(['company_id', 'is_active']);
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('default_branch_id');
            $table->dropColumn([
                'username', 'phone', 'job_title', 'avatar_path',
                'is_active', 'last_login_at', 'deleted_at',
            ]);
        });
    }
};
