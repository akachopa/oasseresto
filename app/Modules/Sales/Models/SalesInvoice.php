<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Models\User;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Customer\Models\Customer;
use App\Modules\Delivery\Models\Delivery;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoice extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'payment_term' => PaymentTermType::class,
            'invoice_date' => 'date',
            'due_date' => 'date',
            'is_tax_inclusive' => 'boolean',
            'subtotal' => 'float',
            'discount_amount' => 'float',
            'tax_amount' => 'float',
            'shipping_cost' => 'float',
            'total' => 'float',
            'paid_amount' => 'float',
            'outstanding_amount' => 'float',
            'cost_of_goods' => 'float',
            'posted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
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

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesman_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPosted(): bool
    {
        return $this->status === DocumentStatus::Posted;
    }

    public function isOverdue(): bool
    {
        return $this->outstanding_amount > 0 && $this->due_date->isPast();
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(now()) : 0;
    }

    public function grossProfit(): float
    {
        return round($this->subtotal - $this->cost_of_goods, 4);
    }

    public function marginPercent(): float
    {
        return $this->subtotal > 0 ? round($this->grossProfit() / $this->subtotal * 100, 4) : 0.0;
    }
}
