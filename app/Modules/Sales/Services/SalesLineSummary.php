<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Core\Models\TaxCode;
use App\Modules\Core\Services\LineCalculator;

/**
 * Ringkasan nilai dokumen untuk form penjualan. Perhitungannya memakai
 * LineCalculator yang sama seperti saat menyimpan, sehingga angka di layar
 * tidak pernah berbeda dari angka tersimpan.
 */
class SalesLineSummary
{
    public function __construct(private readonly LineCalculator $calculator) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: float, discount: float, tax: float, total: float}
     */
    public function of(array $items, bool $taxInclusive = false, float $extraCost = 0): array
    {
        $taxCodes = TaxCode::whereIn('id', array_filter(array_column($items, 'tax_code_id')))
            ->get()
            ->keyBy('id');

        $subtotal = 0.0;
        $discount = 0.0;
        $tax = 0.0;

        foreach ($items as $row) {
            $line = $this->calculator->line(
                quantity: (float) ($row['quantity'] ?? 0),
                unitPrice: (float) ($row['unit_price'] ?? 0),
                discountPercent: (float) ($row['discount_percent'] ?? 0),
                discountAmount: isset($row['discount_amount']) && $row['discount_amount'] !== null
                    ? (float) $row['discount_amount']
                    : null,
                taxCode: ($row['tax_code_id'] ?? null) ? $taxCodes->get($row['tax_code_id']) : null,
                taxInclusive: $taxInclusive,
            );

            $subtotal += $line['base_amount'];
            $discount += $line['discount_amount'];
            $tax += $line['tax_amount'];
        }

        return [
            'subtotal' => round($subtotal, 4),
            'discount' => round($discount, 4),
            'tax' => round($tax, 4),
            'total' => round($subtotal + $tax + $extraCost, 4),
        ];
    }
}
