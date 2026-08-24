<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends BaseModel
{
    use BelongsToCompany;

    /**
     * Alasan wajib dipilih supaya selisih stok selalu bisa dipertanggung
     * jawabkan dan bisa dipetakan ke akun biaya yang tepat (PLAN 29).
     *
     * @return array<string, string>
     */
    public static function reasons(): array
    {
        return [
            'damaged' => 'Barang Rusak',
            'expired' => 'Kedaluwarsa',
            'lost' => 'Hilang / Selisih Kurang',
            'found' => 'Ditemukan / Selisih Lebih',
            'correction' => 'Koreksi Data',
            'opening' => 'Saldo Awal',
        ];
    }

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'adjustment_date' => 'date',
            'total_value' => 'float',
            'posted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
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
}
