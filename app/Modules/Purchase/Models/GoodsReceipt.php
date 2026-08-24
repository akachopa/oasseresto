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

class GoodsReceipt extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'receipt_date' => 'date',
            'total_value' => 'float',
            'posted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
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

    public function isPosted(): bool
    {
        return $this->status === DocumentStatus::Posted;
    }
}
