<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Services;

use App\Modules\Core\Models\TaxCode;

/**
 * Perhitungan nilai baris dan total dokumen pembelian dikumpulkan di satu
 * tempat supaya PO, invoice, dan retur tidak pernah menghitung berbeda.
 */
class LineCalculator
{
    /**
     * @return array{discount_amount: float, base_amount: float, tax_amount: float, line_total: float}
     */
    public function line(
        float $quantity,
        float $unitPrice,
        float $discountPercent = 0,
        ?float $discountAmount = null,
        ?TaxCode $taxCode = null,
        bool $taxInclusive = false,
    ): array {
        $gross = round($quantity * $unitPrice, 4);

        $discount = $discountAmount !== null
            ? round($discountAmount, 4)
            : round($gross * $discountPercent / 100, 4);

        $net = round($gross - $discount, 4);

        if ($taxCode === null || $taxCode->rate <= 0) {
            return [
                'discount_amount' => $discount,
                'base_amount' => $net,
                'tax_amount' => 0.0,
                'line_total' => $net,
            ];
        }

        /*
         * Harga termasuk pajak: nilai baris tetap sebesar yang dibayar dan
         * pajaknya dikeluarkan dari dalam, sehingga total dokumen tidak
         * berubah saat kode pajak inclusive dipakai.
         */
        if ($taxInclusive || $taxCode->is_inclusive) {
            $split = $taxCode->split($net);

            return [
                'discount_amount' => $discount,
                'base_amount' => $split['base'],
                'tax_amount' => $split['tax'],
                'line_total' => $net,
            ];
        }

        $tax = round($net * $taxCode->rate / 100, 4);

        return [
            'discount_amount' => $discount,
            'base_amount' => $net,
            'tax_amount' => $tax,
            'line_total' => round($net + $tax, 4),
        ];
    }
}
