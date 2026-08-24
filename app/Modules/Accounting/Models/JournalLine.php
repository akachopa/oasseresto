<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use App\Modules\Company\Models\Branch;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Core\Models\CostCenter;
use App\Modules\Customer\Models\Customer;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'debit' => 'float',
            'credit' => 'float',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * Lawan transaksi disimpan sebagai tipe pendek supaya buku besar bisa
     * menampilkan customer atau supplier tanpa relasi polimorfik penuh.
     */
    public function partner(): ?Model
    {
        return match ($this->partner_type) {
            'customer' => Customer::find($this->partner_id),
            'supplier' => Supplier::find($this->partner_id),
            default => null,
        };
    }

    public function amount(): float
    {
        return round($this->debit > 0 ? $this->debit : $this->credit, 4);
    }

    public function isDebit(): bool
    {
        return $this->debit > 0;
    }

    /**
     * Nilai bertanda mengikuti sisi normal akun, dipakai laporan laba rugi
     * dan neraca agar tidak perlu logika tanda di setiap laporan.
     */
    public function signedAmount(): float
    {
        return $this->account?->type->normalBalance() === 'credit'
            ? round($this->credit - $this->debit, 4)
            : round($this->debit - $this->credit, 4);
    }
}
