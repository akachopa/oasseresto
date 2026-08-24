<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Models;

use App\Models\User;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\DeliveryStatus;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerAddress;
use App\Modules\Sales\Models\SalesOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'delivery_date' => 'date',
            'total_value' => 'float',
            'picked_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function picker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picked_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Surat jalan yang masih perlu dikerjakan gudang, dipakai layar picking.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            DeliveryStatus::Ready,
            DeliveryStatus::Picking,
            DeliveryStatus::Packed,
        ]);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [DeliveryStatus::Ready, DeliveryStatus::Picking], true);
    }

    public function isPickable(): bool
    {
        return in_array($this->status, [DeliveryStatus::Ready, DeliveryStatus::Picking], true);
    }

    public function isDispatchable(): bool
    {
        return $this->status === DeliveryStatus::Packed;
    }

    public function isCompletable(): bool
    {
        return $this->status === DeliveryStatus::Dispatched;
    }

    public function isDelivered(): bool
    {
        return $this->status === DeliveryStatus::Delivered;
    }

    public function uninvoicedBaseQuantity(): float
    {
        return round(
            $this->items->sum(fn (DeliveryItem $item) => $item->uninvoicedBaseQuantity()),
            4,
        );
    }
}
