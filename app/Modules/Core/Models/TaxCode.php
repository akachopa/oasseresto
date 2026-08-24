<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Modules\Core\Concerns\BelongsToCompany;

class TaxCode extends BaseModel
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'is_inclusive' => 'boolean',
            'for_sales' => 'boolean',
            'for_purchase' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Pisahkan nilai dasar dan nilai pajak dari sebuah amount.
     *
     * @return array{base: float, tax: float}
     */
    public function split(float $amount): array
    {
        if ($this->rate <= 0) {
            return ['base' => round($amount, 2), 'tax' => 0.0];
        }

        if ($this->is_inclusive) {
            $base = $amount / (1 + ($this->rate / 100));

            return ['base' => round($base, 2), 'tax' => round($amount - $base, 2)];
        }

        return ['base' => round($amount, 2), 'tax' => round($amount * $this->rate / 100, 2)];
    }
}
