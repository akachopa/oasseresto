<?php

declare(strict_types=1);

namespace App\Modules\Approval\Models;

use App\Models\User;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\ApprovalStatus;
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStep extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'acted_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRule::class, 'approval_rule_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    /**
     * Langkah bisa ditujukan ke satu user tertentu atau ke siapa pun pemegang
     * role, sehingga approval tidak macet saat satu orang tidak tersedia.
     */
    public function isFor(User $user): bool
    {
        if ($this->approver_user_id !== null) {
            return (int) $this->approver_user_id === (int) $user->getKey();
        }

        return $this->approver_role !== null && $user->hasRole($this->approver_role);
    }

    public function approverLabel(): string
    {
        return $this->approver?->name ?? (string) $this->approver_role;
    }
}
