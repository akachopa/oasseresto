<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'discount_amount' => 'float',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class, 'payment_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class, 'target_id');
    }

    public function payable(): BelongsTo
    {
        return $this->belongsTo(Payable::class, 'target_id');
    }

    /**
     * Dokumen yang dilunasi. Tipe disimpan sebagai string pendek agar tidak
     * bergantung pada nama class (aman saat namespace berubah).
     */
    public function target(): ?Model
    {
        return match ($this->target_type) {
            'receivable' => Receivable::find($this->target_id),
            'payable' => Payable::find($this->target_id),
            default => null,
        };
    }

    /**
     * Nilai yang menutup tagihan: uang yang dibayar ditambah diskon pelunasan
     * dini yang diberikan.
     */
    public function settledAmount(): float
    {
        return round($this->amount + $this->discount_amount, 4);
    }
}
