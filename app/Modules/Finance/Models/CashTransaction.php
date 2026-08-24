<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\User;
use App\Modules\Company\Models\Branch;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends BaseModel
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            'receipt' => 'Penerimaan Customer',
            'payment' => 'Pembayaran Supplier',
            'expense' => 'Biaya',
            'transfer_in' => 'Transfer Masuk',
            'transfer_out' => 'Transfer Keluar',
            'pos' => 'Penjualan Kasir',
            'opening' => 'Saldo Awal',
            'other' => 'Lain-lain',
        ];
    }

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'float',
            'balance_after' => 'float',
        ];
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

    public function isInbound(): bool
    {
        return $this->direction === 'in';
    }

    public function signedAmount(): float
    {
        return $this->isInbound() ? $this->amount : -$this->amount;
    }

    public function categoryLabel(): string
    {
        return self::categories()[$this->category] ?? $this->category;
    }
}
