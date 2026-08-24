<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\InventoryTransactionType;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kartu stok bersifat append-only. Tidak ada update atau delete pada baris
 * yang sudah tercatat; koreksi selalu berupa gerakan baru (PLAN 24).
 */
class StockLedger extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'transaction_type' => InventoryTransactionType::class,
            'transaction_date' => 'date',
            'transaction_at' => 'datetime',
            'quantity' => 'float',
            'base_quantity' => 'float',
            'unit_cost' => 'float',
            'total_cost' => 'float',
            'balance_quantity' => 'float',
            'balance_value' => 'float',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
