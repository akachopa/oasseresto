<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use App\Modules\Company\Models\Branch;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receipt extends BaseModel
{
    use BelongsToCompany;

    /**
     * Giro dan cek belum menambah kas sampai cair, sehingga metodenya
     * dibedakan agar proyeksi kas tidak terlalu optimistis (PLAN 31).
     *
     * @return array<string, string>
     */
    public static function methods(): array
    {
        return [
            'cash' => 'Tunai',
            'transfer' => 'Transfer Bank',
            'check' => 'Cek',
            'giro' => 'Giro',
            'card' => 'Kartu',
        ];
    }

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'receipt_date' => 'date',
            'cleared_date' => 'date',
            'amount' => 'float',
            'allocated_amount' => 'float',
            'discount_amount' => 'float',
            'posted_at' => 'datetime',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id')
            ->where('payment_type', 'receipt');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
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

    /**
     * Uang yang belum dialokasikan ke invoice tetap menjadi titipan customer,
     * bukan pelunasan, sampai dialokasikan.
     */
    public function unallocatedAmount(): float
    {
        return max(0, round($this->amount - $this->allocated_amount, 4));
    }

    public function requiresClearing(): bool
    {
        return in_array($this->method, ['check', 'giro'], true);
    }
}
