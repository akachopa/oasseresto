<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Models\User;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesReturn extends BaseModel
{
    use BelongsToCompany;

    /**
     * @return array<string, string>
     */
    public static function reasons(): array
    {
        return [
            'damaged' => 'Barang Rusak',
            'expired' => 'Kedaluwarsa',
            'wrong_item' => 'Salah Kirim',
            'not_as_ordered' => 'Tidak Sesuai Pesanan',
            'excess' => 'Kelebihan Kirim',
        ];
    }

    /**
     * Retur bisa mengurangi piutang (credit note) atau dikembalikan tunai.
     *
     * @return array<string, string>
     */
    public static function settlements(): array
    {
        return [
            'credit_note' => 'Kurangi Piutang',
            'refund' => 'Pengembalian Uang',
            'replacement' => 'Tukar Barang',
        ];
    }

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'return_date' => 'date',
            'restock' => 'boolean',
            'subtotal' => 'float',
            'tax_amount' => 'float',
            'total' => 'float',
            'cost_of_goods' => 'float',
            'posted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reasonLabel(): string
    {
        return self::reasons()[$this->reason] ?? $this->reason;
    }

    public function settlementLabel(): string
    {
        return self::settlements()[$this->settlement] ?? $this->settlement;
    }
}
