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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'payment_term' => PaymentTermType::class,
            'quotation_date' => 'date',
            'valid_until' => 'date',
            'is_tax_inclusive' => 'boolean',
            'subtotal' => 'float',
            'discount_amount' => 'float',
            'tax_amount' => 'float',
            'total' => 'float',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
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

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesman_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null
            && $this->valid_until->isPast()
            && $this->status !== DocumentStatus::Completed;
    }

    /**
     * Penawaran boleh dijadikan order selama sudah dikirim ke customer dan
     * belum kedaluwarsa, sehingga harga yang dijanjikan tetap berlaku.
     */
    public function isConvertible(): bool
    {
        return in_array($this->status, [DocumentStatus::Submitted, DocumentStatus::Approved], true)
            && ! $this->isExpired();
    }
}
