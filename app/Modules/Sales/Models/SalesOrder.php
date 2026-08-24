<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Models\User;
use App\Modules\Approval\Contracts\Approvable;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerAddress;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Sales\Services\SalesOrderService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends BaseModel implements Approvable
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'payment_term' => PaymentTermType::class,
            'order_date' => 'date',
            'requested_delivery_date' => 'date',
            'is_tax_inclusive' => 'boolean',
            'requires_margin_approval' => 'boolean',
            'subtotal' => 'float',
            'discount_amount' => 'float',
            'tax_amount' => 'float',
            'shipping_cost' => 'float',
            'total' => 'float',
            'estimated_cost' => 'float',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Barang hanya boleh keluar untuk order yang sudah disetujui dan belum
     * tuntas, sehingga stok tidak pernah dikirim tanpa dasar (PLAN 27).
     */
    public function isDeliverable(): bool
    {
        return in_array($this->status, [DocumentStatus::Approved, DocumentStatus::PartiallyProcessed], true);
    }

    public function isInvoiceable(): bool
    {
        return in_array(
            $this->status,
            [DocumentStatus::Approved, DocumentStatus::PartiallyProcessed, DocumentStatus::Completed],
            true,
        );
    }

    public function outstandingBaseQuantity(): float
    {
        return round(
            $this->items->sum(fn (SalesOrderItem $item) => $item->outstandingBaseQuantity()),
            4,
        );
    }

    public function marginPercent(): float
    {
        return $this->subtotal > 0
            ? round(($this->subtotal - $this->estimated_cost) / $this->subtotal * 100, 4)
            : 0.0;
    }

    public function approvalDocumentType(): string
    {
        return 'sales_order';
    }

    public function approvalTitle(): string
    {
        return 'Sales Order '.$this->number.' - '.($this->customer?->name ?? '');
    }

    public function approvalAmount(): float
    {
        return (float) $this->total;
    }

    public function approvalBranchId(): ?int
    {
        return $this->branch_id;
    }

    public function approvalUrl(): ?string
    {
        return route('sales.orders.detail', $this);
    }

    /**
     * Reservasi stok dijalankan dari callback ini, bukan dari controller,
     * supaya order yang disetujui lewat halaman approval mana pun tetap
     * menahan stoknya (PLAN 27).
     */
    public function onApprovalApproved(ApprovalRequest $request): void
    {
        $this->status = DocumentStatus::Approved;
        $this->approved_by = $request->steps->last()?->acted_by;
        $this->approved_at = now();
        $this->save();

        app(SalesOrderService::class)->handleApproved($this);
    }

    public function onApprovalRejected(ApprovalRequest $request): void
    {
        $this->status = DocumentStatus::Rejected;
        $this->rejection_reason = $request->steps->last()?->note;
        $this->save();
    }
}
