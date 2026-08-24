<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Models;

use App\Modules\Company\Models\Branch;
use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyBusinessMetric extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'sales_amount' => 'float',
            'cogs_amount' => 'float',
            'gross_profit' => 'float',
            'sales_return_amount' => 'float',
            'receipt_amount' => 'float',
            'payment_amount' => 'float',
            'expense_amount' => 'float',
            'cash_balance' => 'float',
            'ar_outstanding' => 'float',
            'ar_overdue' => 'float',
            'ap_outstanding' => 'float',
            'ap_overdue' => 'float',
            'inventory_value' => 'float',
            'computed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function marginPercent(): float
    {
        return $this->sales_amount > 0
            ? round($this->gross_profit / $this->sales_amount * 100, 4)
            : 0.0;
    }
}
