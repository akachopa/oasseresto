<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Models\User;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'opening_cash' => 'float',
            'cash_sales' => 'float',
            'non_cash_sales' => 'float',
            'credit_sales' => 'float',
            'cash_in' => 'float',
            'cash_out' => 'float',
            'expected_cash' => 'float',
            'counted_cash' => 'float',
            'difference' => 'float',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class, 'cashier_shift_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Kas yang seharusnya ada di drawer: modal awal ditambah penjualan tunai
     * dan mutasi kas manual selama shift (PLAN 28).
     */
    public function refreshExpectedCash(): self
    {
        $this->expected_cash = round(
            $this->opening_cash + $this->cash_sales + $this->cash_in - $this->cash_out,
            4,
        );

        if ($this->counted_cash !== null) {
            $this->difference = round($this->counted_cash - $this->expected_cash, 4);
        }

        return $this;
    }

    public function totalSales(): float
    {
        return round($this->cash_sales + $this->non_cash_sales + $this->credit_sales, 4);
    }
}
