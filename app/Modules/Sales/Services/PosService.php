<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Models\User;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\InventoryTransactionType;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Services\StockMovement;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Services\PricingService;
use App\Modules\Sales\Models\CashierShift;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * POS ringkas untuk penjualan tunai di counter (PLAN 28). Setiap transaksi
 * langsung menjadi invoice terposting: stok keluar, uang masuk ke shift kasir,
 * dan sisanya jadi piutang bila customer bayar sebagian.
 */
class PosService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly SalesInvoiceService $invoices,
        private readonly StockService $stock,
        private readonly PricingService $pricing,
    ) {}

    public function openShift(User $cashier, int $warehouseId, float $openingCash = 0): CashierShift
    {
        return DB::transaction(function () use ($cashier, $warehouseId, $openingCash): CashierShift {
            if ($this->activeShift($cashier) !== null) {
                throw new RuntimeException('Kasir ini masih punya shift yang terbuka.');
            }

            $warehouse = Warehouse::findOrFail($warehouseId);
            $now = now();

            $shift = new CashierShift([
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->getKey(),
                'user_id' => $cashier->getKey(),
                'status' => 'open',
                'opening_cash' => $openingCash,
                'opened_at' => $now,
            ]);

            $shift->number = $this->numbers->next('cashier_shift', $warehouse->branch_id, $now);
            $shift->refreshExpectedCash()->save();

            return $shift;
        });
    }

    public function closeShift(CashierShift $shift, float $countedCash, ?string $note = null): CashierShift
    {
        if (! $shift->isOpen()) {
            throw new RuntimeException('Shift ini sudah ditutup.');
        }

        $shift->counted_cash = $countedCash;
        $shift->note = $note;
        $shift->status = 'closed';
        $shift->closed_at = now();
        $shift->refreshExpectedCash()->save();

        return $shift;
    }

    public function activeShift(User $cashier): ?CashierShift
    {
        return CashierShift::where('user_id', $cashier->getKey())
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    /**
     * Transaksi kasir: barang keluar dari gudang counter dan invoice langsung
     * terposting supaya laporan kas dan stok selalu sinkron.
     *
     * @param  array<int, array{product_id: int, unit_id?: ?int, quantity: float, unit_price?: ?float, discount_amount?: ?float}>  $items
     */
    public function checkout(
        CashierShift $shift,
        Customer $customer,
        array $items,
        float $paidAmount,
        string $paymentMethod = 'cash',
    ): SalesInvoice {
        return DB::transaction(function () use ($shift, $customer, $items, $paidAmount, $paymentMethod): SalesInvoice {
            if (! $shift->isOpen()) {
                throw new RuntimeException('Shift kasir sudah ditutup; buka shift baru untuk berjualan.');
            }

            if ($items === []) {
                throw new RuntimeException('Tidak ada barang untuk dibayar.');
            }

            $lines = $this->resolveLines($shift, $customer, $items);

            $invoice = $this->invoices->save(null, [
                'customer_id' => $customer->getKey(),
                'warehouse_id' => $shift->warehouse_id,
                'branch_id' => $shift->branch_id,
                'cashier_shift_id' => $shift->getKey(),
                'invoice_date' => now()->toDateString(),
                'payment_term' => PaymentTermType::Cash->value,
                'source' => 'pos',
            ], $lines['items']);

            if ($paidAmount + 0.0001 < $invoice->total && ! $customer->effectivePaymentTerm()->isCredit()) {
                throw new RuntimeException('Pembayaran kurang dari total dan customer ini tidak punya termin kredit.');
            }

            $this->issueStock($invoice, $shift);

            $invoice = $this->invoices->post($invoice, $paidAmount);

            $this->recordPayment($shift, $invoice, $paidAmount, $paymentMethod);

            return $invoice;
        });
    }

    /**
     * Kembalian dihitung terhadap total invoice, bukan terhadap subtotal,
     * supaya pajak dan diskon ikut diperhitungkan.
     */
    public function change(SalesInvoice $invoice, float $paidAmount): float
    {
        return round(max(0, $paidAmount - $invoice->total), 4);
    }

    public function recordCashMovement(CashierShift $shift, float $amount, string $direction, ?string $note = null): CashierShift
    {
        if (! $shift->isOpen()) {
            throw new RuntimeException('Shift kasir sudah ditutup.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Nilai mutasi kas harus lebih besar dari nol.');
        }

        if ($direction === 'in') {
            $shift->cash_in = round($shift->cash_in + $amount, 4);
        } else {
            $shift->cash_out = round($shift->cash_out + $amount, 4);
        }

        $shift->note = $note ?? $shift->note;
        $shift->refreshExpectedCash()->save();

        return $shift;
    }

    /**
     * @param  array<int, array{product_id: int, unit_id?: ?int, quantity: float, unit_price?: ?float, discount_amount?: ?float}>  $items
     * @return array{items: array<int, array<string, mixed>>}
     */
    private function resolveLines(CashierShift $shift, Customer $customer, array $items): array
    {
        $lines = [];

        foreach ($items as $row) {
            $product = Product::findOrFail($row['product_id']);
            $unitId = (int) ($row['unit_id'] ?? $product->sales_unit_id ?? $product->base_unit_id);
            $quantity = (float) $row['quantity'];

            if ($quantity <= 0) {
                throw new RuntimeException("Kuantitas {$product->sku} harus lebih besar dari nol.");
            }

            // Harga kasir tetap melewati PricingService supaya level harga dan
            // aturan promo berlaku sama seperti penjualan lewat sales order.
            $quote = $this->pricing->quote(
                product: $product,
                quantity: $quantity,
                customer: $customer,
                unitId: $unitId,
                branchId: $shift->branch_id,
                paymentTerm: PaymentTermType::Cash,
            );

            $lines[] = [
                'product_id' => $product->getKey(),
                'unit_id' => $unitId,
                'quantity' => $quantity,
                'unit_price' => (float) ($row['unit_price'] ?? $quote->price),
                'unit_cost' => $quote->unitCost,
                'discount_amount' => isset($row['discount_amount']) ? (float) $row['discount_amount'] : null,
                'tax_code_id' => $product->tax_code_id,
            ];
        }

        return ['items' => $lines];
    }

    /**
     * Barang POS keluar tanpa surat jalan karena diserahkan langsung di
     * counter; kartu stok tetap menunjuk invoice sebagai dokumen sumber.
     */
    private function issueStock(SalesInvoice $invoice, CashierShift $shift): void
    {
        $invoice->load('items.product');

        $costTotal = 0.0;

        foreach ($invoice->items as $item) {
            $ledgers = $this->stock->issue(new StockMovement(
                product: $item->product,
                warehouseId: (int) $shift->warehouse_id,
                type: InventoryTransactionType::Sale,
                quantity: $item->base_quantity,
                unitId: (int) $item->product->base_unit_id,
                documentType: 'pos_sale',
                documentId: (int) $invoice->getKey(),
                documentNumber: $invoice->number,
                date: $invoice->invoice_date,
                branchId: $invoice->branch_id,
            ));

            $cost = round((float) collect($ledgers)->sum('total_cost'), 4);

            $item->unit_cost = $item->base_quantity > 0 ? round($cost / $item->base_quantity, 4) : 0.0;
            $item->save();

            $costTotal += $cost;
        }

        $invoice->cost_of_goods = round($costTotal, 4);
        $invoice->save();
    }

    private function recordPayment(CashierShift $shift, SalesInvoice $invoice, float $paidAmount, string $method): void
    {
        $paid = round(min($paidAmount, $invoice->total), 4);
        $credit = round($invoice->total - $paid, 4);

        if ($method === 'cash') {
            $shift->cash_sales = round($shift->cash_sales + $paid, 4);
        } else {
            $shift->non_cash_sales = round($shift->non_cash_sales + $paid, 4);
        }

        if ($credit > 0) {
            $shift->credit_sales = round($shift->credit_sales + $credit, 4);
        }

        $shift->transaction_count++;
        $shift->refreshExpectedCash()->save();
    }

    /**
     * Ringkasan shift untuk layar tutup kasir.
     *
     * @return array<string, float|int>
     */
    public function summary(CashierShift $shift): array
    {
        $invoices = SalesInvoice::where('cashier_shift_id', $shift->getKey())
            ->where('status', DocumentStatus::Posted)
            ->get();

        return [
            'transaction_count' => $invoices->count(),
            'gross_sales' => round((float) $invoices->sum('total'), 4),
            'cost_of_goods' => round((float) $invoices->sum('cost_of_goods'), 4),
            'cash_sales' => $shift->cash_sales,
            'non_cash_sales' => $shift->non_cash_sales,
            'credit_sales' => $shift->credit_sales,
            'expected_cash' => $shift->expected_cash,
        ];
    }
}
