<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\BaseModel;
use App\Modules\Product\Models\PriceLevel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerGroup extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'default_payment_term' => PaymentTermType::class,
            'default_credit_limit' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function priceLevel(): BelongsTo
    {
        return $this->belongsTo(PriceLevel::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
