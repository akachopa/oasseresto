<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Models\User;
use App\Modules\Core\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportJob extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed'], true);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'products' => 'Produk',
            'customers' => 'Customer',
            'suppliers' => 'Supplier',
            default => $this->type,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Antrian',
            'processing' => 'Diproses',
            'completed' => 'Selesai',
            'failed' => 'Gagal',
            default => $this->status,
        };
    }
}
