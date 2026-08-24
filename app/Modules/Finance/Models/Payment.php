<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use App\Modules\Company\Models\Branch;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends BaseModel
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    public static function methods(): array
    {
        return [
            'cash' => 'Tunai',
            'transfer' => 'Transfer Bank',
            'check' => 'Cek',
            'giro' => 'Giro',
        ];
    }

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'payment_date' => 'date',
            'amount' => 'float',
            'allocated_amount' => 'float',
            'discount_amount' => 'float',
            'posted_at' => 'datetime',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id')
            ->where('payment_type', 'payment');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function methodLabel(): string
    {
        return self::methods()[$this->method] ?? $this->method;
    }

    public function isPosted(): bool
    {
        return $this->status === DocumentStatus::Posted;
    }

    public function unallocatedAmount(): float
    {
        return max(0, round($this->amount - $this->allocated_amount, 4));
    }
}
