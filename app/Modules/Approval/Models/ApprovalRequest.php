<?php

declare(strict_types=1);

namespace App\Modules\Approval\Models;

use App\Models\User;
use App\Modules\Approval\Contracts\Approvable;
use App\Modules\Company\Models\Branch;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\ApprovalStatus;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Models\PurchaseRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalRequest extends BaseModel
{
    use BelongsToCompany;

    /**
     * Peta jenis dokumen ke model. Engine approval memakai peta ini agar tidak
     * menyimpan nama class di database (rapuh bila namespace berubah).
     *
     * @var array<string, class-string<Approvable&Model>>
     */
    public const DOCUMENTS = [
        'purchase_request' => PurchaseRequest::class,
        'purchase_order' => PurchaseOrder::class,
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'amount' => 'float',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('sequence');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ApprovalStatus::Pending);
    }

    public function isPending(): bool
    {
        return $this->status === ApprovalStatus::Pending;
    }

    public function currentStep(): ?ApprovalStep
    {
        return $this->steps->firstWhere('sequence', $this->current_step);
    }

    /**
     * Dokumen sumber dimuat lewat peta DOCUMENTS; null bila jenisnya belum
     * terdaftar atau dokumennya sudah dihapus.
     */
    public function document(): ?Model
    {
        $class = self::DOCUMENTS[$this->document_type] ?? null;

        if ($class === null) {
            return null;
        }

        return $class::withoutGlobalScopes()->find($this->document_id);
    }
}
