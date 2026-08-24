<?php

declare(strict_types=1);

namespace App\Modules\Product\Services;

use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Customer\Models\Customer;
use App\Modules\Product\Models\PriceRule;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductPrice;
use Illuminate\Support\Carbon;

/**
 * Satu-satunya tempat harga jual dihitung (PLAN 15). Urutan penentuan:
 * price rule paling spesifik -> harga level customer -> harga dasar produk.
 */
class PricingService
{
    public function quote(
        Product $product,
        float $quantity = 1,
        ?Customer $customer = null,
        ?int $unitId = null,
        ?int $branchId = null,
        ?PaymentTermType $paymentTerm = null,
        ?Carbon $date = null,
    ): PriceQuote {
        $date ??= Carbon::now();
        $unitId ??= (int) ($product->sales_unit_id ?? $product->base_unit_id);
        $paymentTerm ??= $customer?->effectivePaymentTerm();
        $priceLevelId = $customer?->effectivePriceLevelId();

        $conversion = $product->conversionFor($unitId);
        $listPrice = $this->listPrice($product, $priceLevelId, $unitId, $conversion);
        $unitCost = round((float) $product->average_cost * $conversion, 4);

        $baseQuantity = round($quantity * $conversion, 6);

        $rule = $this->bestRule($product, $baseQuantity, $customer, $unitId, $branchId, $paymentTerm, $date);

        if ($rule !== null) {
            return new PriceQuote(
                listPrice: $listPrice,
                price: $rule->applyTo($listPrice),
                unitCost: $unitCost,
                source: 'rule',
                rule: $rule,
            );
        }

        return new PriceQuote(
            listPrice: $listPrice,
            price: $listPrice,
            unitCost: $unitCost,
            source: $priceLevelId ? 'price_level' : 'base_price',
        );
    }

    /**
     * Harga acuan pada satuan yang diminta. Bila level harga tidak punya
     * entri untuk satuan tersebut, harga base unit dikalikan konversi.
     */
    private function listPrice(Product $product, ?int $priceLevelId, int $unitId, float $conversion): float
    {
        if ($priceLevelId !== null) {
            $exact = ProductPrice::where('product_id', $product->getKey())
                ->where('price_level_id', $priceLevelId)
                ->where('unit_id', $unitId)
                ->where('is_active', true)
                ->value('price');

            if ($exact !== null) {
                return (float) $exact;
            }

            $baseLevelPrice = ProductPrice::where('product_id', $product->getKey())
                ->where('price_level_id', $priceLevelId)
                ->where('unit_id', $product->base_unit_id)
                ->where('is_active', true)
                ->value('price');

            if ($baseLevelPrice !== null) {
                return round((float) $baseLevelPrice * $conversion, 4);
            }
        }

        return round((float) $product->base_price * $conversion, 4);
    }

    private function bestRule(
        Product $product,
        float $baseQuantity,
        ?Customer $customer,
        int $unitId,
        ?int $branchId,
        ?PaymentTermType $paymentTerm,
        Carbon $date,
    ): ?PriceRule {
        $rules = PriceRule::query()
            ->effectiveOn($date)
            ->where(fn ($q) => $q->whereNull('product_id')->orWhere('product_id', $product->getKey()))
            ->where(fn ($q) => $q->whereNull('product_category_id')->orWhere('product_category_id', $product->product_category_id))
            ->where(fn ($q) => $q->whereNull('unit_id')->orWhere('unit_id', $unitId))
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->where(fn ($q) => $q->whereNull('customer_id')->orWhere('customer_id', $customer?->getKey()))
            ->where(fn ($q) => $q->whereNull('customer_group_id')->orWhere('customer_group_id', $customer?->customer_group_id))
            ->where(fn ($q) => $q->whereNull('price_level_id')->orWhere('price_level_id', $customer?->effectivePriceLevelId()))
            ->where(fn ($q) => $q->whereNull('payment_term')->orWhere('payment_term', $paymentTerm?->value))
            ->where('min_quantity', '<=', $baseQuantity)
            ->where(fn ($q) => $q->whereNull('max_quantity')->orWhere('max_quantity', '>=', $baseQuantity))
            ->get();

        if ($rules->isEmpty()) {
            return null;
        }

        // Aturan paling spesifik menang; bila skor sama, harga terendah dipakai.
        return $rules
            ->sortByDesc(fn (PriceRule $rule) => $rule->specificity())
            ->groupBy(fn (PriceRule $rule) => $rule->specificity())
            ->first()
            ->sortBy(fn (PriceRule $rule) => $rule->applyTo(1_000_000))
            ->first();
    }
}
