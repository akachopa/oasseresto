<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Core\Services\LineCalculator;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Customer\Models\Customer;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Services\PricingService;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Models\QuotationItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Penawaran adalah harga yang dijanjikan ke customer sebelum ada komitmen
 * (PLAN 25). Harga diambil dari PricingService supaya konsisten dengan order.
 */
class QuotationService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly LineCalculator $calculator,
        private readonly PricingService $pricing,
        private readonly ScopeManager $scope,
    ) {}

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, unit_price?: ?float, discount_percent?: ?float, tax_code_id?: ?int, note?: ?string}>  $items
     */
    public function save(?Quotation $quotation, array $attributes, array $items): Quotation
    {
        return DB::transaction(function () use ($quotation, $attributes, $items): Quotation {
            if ($quotation !== null && ! $quotation->status->isEditable()) {
                throw new RuntimeException('Penawaran yang sudah dikirim tidak bisa diubah.');
            }

            $customer = Customer::findOrFail($attributes['customer_id']);
            $date = Carbon::parse($attributes['quotation_date']);

            $quotation ??= new Quotation([
                'status' => DocumentStatus::Draft,
                'created_by' => Auth::id(),
            ]);

            $quotation->fill([
                'customer_id' => $customer->getKey(),
                'branch_id' => $attributes['branch_id'] ?? $this->scope->branchId() ?? $customer->branch_id,
                'warehouse_id' => $attributes['warehouse_id'] ?? null,
                'quotation_date' => $date->toDateString(),
                'valid_until' => $attributes['valid_until'] ?? $date->copy()->addDays(14)->toDateString(),
                'payment_term' => $attributes['payment_term'] ?? $customer->effectivePaymentTerm(),
                'is_tax_inclusive' => (bool) ($attributes['is_tax_inclusive'] ?? false),
                'salesman_id' => $attributes['salesman_id'] ?? $customer->salesman_id ?? Auth::id(),
                'note' => $attributes['note'] ?? null,
                'terms' => $attributes['terms'] ?? null,
            ]);

            $quotation->number ??= $this->numbers->next('quotation', $quotation->branch_id, $date);
            $quotation->save();

            $this->syncItems($quotation, $items);

            return $quotation->refresh();
        });
    }

    /**
     * Kirim penawaran ke customer. Setelah terkirim, harga dianggap mengikat
     * sampai tanggal berlaku berakhir.
     */
    public function send(Quotation $quotation): Quotation
    {
        if (! $quotation->status->isEditable()) {
            throw new RuntimeException('Penawaran ini sudah dikirim.');
        }

        $quotation->load('items');

        if ($quotation->items->isEmpty()) {
            throw new RuntimeException('Penawaran tanpa barang tidak bisa dikirim.');
        }

        $quotation->status = DocumentStatus::Submitted;
        $quotation->sent_at = now();
        $quotation->save();

        return $quotation;
    }

    public function accept(Quotation $quotation): Quotation
    {
        if ($quotation->status !== DocumentStatus::Submitted) {
            throw new RuntimeException('Hanya penawaran terkirim yang bisa ditandai diterima.');
        }

        $quotation->status = DocumentStatus::Approved;
        $quotation->accepted_at = now();
        $quotation->save();

        return $quotation;
    }

    public function markConverted(Quotation $quotation): Quotation
    {
        $quotation->status = DocumentStatus::Completed;
        $quotation->accepted_at ??= now();
        $quotation->save();

        return $quotation;
    }

    public function cancel(Quotation $quotation): Quotation
    {
        if ($quotation->orders()->exists()) {
            throw new RuntimeException('Penawaran yang sudah menjadi order tidak bisa dibatalkan.');
        }

        $quotation->status = DocumentStatus::Cancelled;
        $quotation->save();

        return $quotation;
    }

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, unit_price?: ?float, discount_percent?: ?float, tax_code_id?: ?int, note?: ?string}>  $items
     */
    private function syncItems(Quotation $quotation, array $items): void
    {
        $quotation->items()->delete();
        $customer = $quotation->customer;

        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;

        foreach ($items as $row) {
            $product = Product::findOrFail($row['product_id']);
            $unitId = (int) ($row['unit_id'] ?: $product->base_unit_id);
            $taxCodeId = $row['tax_code_id'] ?? $product->tax_code_id;
            $taxCode = $taxCodeId ? TaxCode::find($taxCodeId) : null;

            $quote = $this->pricing->quote(
                product: $product,
                quantity: (float) $row['quantity'],
                customer: $customer,
                unitId: $unitId,
                branchId: $quotation->branch_id,
                paymentTerm: $quotation->payment_term instanceof PaymentTermType ? $quotation->payment_term : null,
                date: $quotation->quotation_date,
            );

            $unitPrice = isset($row['unit_price']) && $row['unit_price'] !== null && $row['unit_price'] !== ''
                ? (float) $row['unit_price']
                : $quote->price;

            $line = $this->calculator->line(
                quantity: (float) $row['quantity'],
                unitPrice: $unitPrice,
                discountPercent: (float) ($row['discount_percent'] ?? 0),
                taxCode: $taxCode,
                taxInclusive: (bool) $quotation->is_tax_inclusive,
            );

            QuotationItem::create([
                'company_id' => $quotation->company_id,
                'quotation_id' => $quotation->getKey(),
                'product_id' => $product->getKey(),
                'unit_id' => $unitId,
                'tax_code_id' => $taxCode?->getKey(),
                'quantity' => $row['quantity'],
                'base_quantity' => $product->toBaseQuantity((float) $row['quantity'], $unitId),
                'list_price' => $quote->listPrice,
                'unit_price' => $unitPrice,
                'unit_cost' => $quote->unitCost,
                'discount_percent' => $row['discount_percent'] ?? 0,
                'discount_amount' => $line['discount_amount'],
                'tax_amount' => $line['tax_amount'],
                'line_total' => $line['line_total'],
                'note' => $row['note'] ?? null,
            ]);

            $subtotal += $line['base_amount'];
            $discountTotal += $line['discount_amount'];
            $taxTotal += $line['tax_amount'];
        }

        $quotation->subtotal = round($subtotal, 4);
        $quotation->discount_amount = round($discountTotal, 4);
        $quotation->tax_amount = round($taxTotal, 4);
        $quotation->total = round($subtotal + $taxTotal, 4);
        $quotation->save();
    }
}
