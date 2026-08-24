<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Models;

use App\Models\User;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturn extends BaseModel
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
            'quality' => 'Kualitas Tidak Sesuai',
            'excess' => 'Kelebihan Kirim',
        ];
    }

    /**
     * Retur bisa mengurangi hutang (credit note) atau ditukar barang.
     *
     * @return array<string, string>
     */
    public static function settlements(): array
    {
        return [
            'credit_note' => 'Kurangi Hutang',
            'refund' => 'Pengembalian Uang',
            'replacement' => 'Tukar Barang',
        ];
    }

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'return_date' => 'date',
            'subtotal' => 'float',
            'tax_amount' => 'float',
            'total' => 'float',
            'posted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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
