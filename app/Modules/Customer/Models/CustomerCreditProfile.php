<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCreditProfile extends BaseModel
{
    use BelongsToCompany;

    protected $table = 'customer_credit_profiles';

    protected function casts(): array
    {
        return [
            'last_order_date' => 'date',
            'last_payment_date' => 'date',
            'total_purchase' => 'float',
            'average_invoice' => 'float',
            'average_days_to_pay' => 'float',
            'max_overdue_days' => 'float',
            'is_dormant' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Klasifikasi perilaku bayar untuk ditampilkan di halaman customer (PLAN 16).
     */
    public function behaviorLabel(): string
    {
        return match ($this->payment_behavior) {
            'excellent' => 'Selalu tepat waktu',
            'good' => 'Umumnya tepat waktu',
            'fair' => 'Sering terlambat',
            'poor' => 'Bermasalah',
            default => 'Belum ada riwayat',
        };
    }

    public function behaviorColor(): string
    {
        return match ($this->payment_behavior) {
            'excellent', 'good' => 'success',
            'fair' => 'warning',
            'poor' => 'danger',
            default => 'muted',
        };
    }
}
