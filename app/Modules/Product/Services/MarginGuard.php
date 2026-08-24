<?php

declare(strict_types=1);

namespace App\Modules\Product\Services;

use App\Modules\Product\Models\Product;

/**
 * Menjaga penjualan tidak jatuh di bawah margin minimum (PLAN 18).
 * Mode diambil dari config: block, warn, atau approval.
 */
class MarginGuard
{
    /**
     * @return array{allowed: bool, requires_approval: bool, margin_percent: float, minimum: float, message: ?string}
     */
    public function evaluate(Product $product, float $price, float $unitCost): array
    {
        $minimum = $product->minimumMargin();
        $marginPercent = $price > 0 ? round(($price - $unitCost) / $price * 100, 4) : 0.0;
        $mode = (string) config('oasse.pricing.margin_guard', 'approval');

        if ($marginPercent >= $minimum) {
            return [
                'allowed' => true,
                'requires_approval' => false,
                'margin_percent' => $marginPercent,
                'minimum' => $minimum,
                'message' => null,
            ];
        }

        $message = sprintf(
            'Margin %s%% di bawah minimum %s%% untuk %s.',
            number_format($marginPercent, 2, ',', '.'),
            number_format($minimum, 2, ',', '.'),
            $product->sku,
        );

        return match ($mode) {
            'block' => [
                'allowed' => false,
                'requires_approval' => false,
                'margin_percent' => $marginPercent,
                'minimum' => $minimum,
                'message' => $message.' Harga ini tidak diizinkan.',
            ],
            'warn' => [
                'allowed' => true,
                'requires_approval' => false,
                'margin_percent' => $marginPercent,
                'minimum' => $minimum,
                'message' => $message,
            ],
            default => [
                'allowed' => true,
                'requires_approval' => true,
                'margin_percent' => $marginPercent,
                'minimum' => $minimum,
                'message' => $message.' Butuh persetujuan supervisor.',
            ],
        };
    }

    /**
     * Menjual di bawah harga pokok selalu memerlukan approval, apa pun mode-nya.
     */
    public function isBelowCost(float $price, float $unitCost): bool
    {
        return $price < $unitCost;
    }
}
