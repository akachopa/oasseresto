<?php

declare(strict_types=1);

namespace App\Modules\Product\Services;

use App\Modules\Product\Models\PriceRule;

/**
 * Hasil kalkulasi harga beserta alasannya, sehingga UI bisa menjelaskan
 * "kenapa harganya begini" tanpa menebak (PLAN 15).
 */
readonly class PriceQuote
{
    public function __construct(
        public float $listPrice,
        public float $price,
        public float $unitCost,
        public string $source,
        public ?PriceRule $rule = null,
    ) {}

    public function discountAmount(): float
    {
        return round($this->listPrice - $this->price, 4);
    }

    public function discountPercent(): float
    {
        return $this->listPrice > 0
            ? round(($this->listPrice - $this->price) / $this->listPrice * 100, 4)
            : 0.0;
    }

    public function marginAmount(): float
    {
        return round($this->price - $this->unitCost, 4);
    }

    /**
     * Margin dihitung terhadap harga jual (gross margin), bukan terhadap cost.
     */
    public function marginPercent(): float
    {
        return $this->price > 0
            ? round(($this->price - $this->unitCost) / $this->price * 100, 4)
            : 0.0;
    }

    public function explanation(): string
    {
        return match ($this->source) {
            'rule' => 'Aturan harga: '.($this->rule?->name ?? '-'),
            'price_level' => 'Harga level customer',
            'base_price' => 'Harga dasar produk',
            default => 'Harga manual',
        };
    }
}
