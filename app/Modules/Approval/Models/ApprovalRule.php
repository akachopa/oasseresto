<?php

declare(strict_types=1);

namespace App\Modules\Approval\Models;

use App\Models\User;
use App\Modules\Company\Models\Branch;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRule extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'min_amount' => 'float',
            'max_amount' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Aturan berlaku bila jenis pemicunya cocok, nilai dokumen masuk rentang,
     * dan cabangnya sesuai (aturan tanpa cabang berlaku untuk semua cabang).
     */
    public function matches(string $trigger, float $amount, ?int $branchId): bool
    {
        if ($this->trigger !== 'always' && $this->trigger !== $trigger) {
            return false;
        }

        if ($this->branch_id !== null && $this->branch_id !== $branchId) {
            return false;
        }

        if ($amount < $this->min_amount) {
            return false;
        }

        return $this->max_amount === null || $amount <= $this->max_amount;
    }

    public function approverLabel(): string
    {
        return $this->approver?->name ?? (string) $this->approver_role;
    }
}
