<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Core\Services\LineCalculator;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerCreditProfile;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliveryItem;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Product\Models\Product;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use App\Modules\Sales\Models\SalesOrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Invoice penjualan mengubah barang yang sudah dikirim menjadi piutang
 * (PLAN 29). Posting invoice membuat satu baris piutang; nilainya tidak
 * pernah dobel karena receivables unik per dokumen sumber.
 */
class SalesInvoiceService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly LineCalculator $calculator,
        private readonly CreditControlService $credit,
    ) {}

    /**
     * Baris invoice disiapkan dari surat jalan yang belum ditagih, sehingga
     * tagihan selalu bersandar pada barang yang benar-benar keluar.
     *
     * @return array<int, array<string, mixed>>
     */
    public function draftItemsFromDelivery(Delivery $delivery): array
    {
        $delivery->load('items.product', 'items.orderItem');

        return $delivery->items
            ->filter(fn (DeliveryItem $item) => $item->uninvoicedBaseQuantity() > 0)
            ->map(fn (DeliveryItem $item) => [
                'delivery_item_id' => $item->getKey(),
                'sales_order_item_id' => $item->sales_order_item_id,
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id,
                'quantity' => $item->product->fromBaseQuantity($item->uninvoicedBaseQuantity(), $item->unit_id),
                'unit_price' => $item->orderItem?->unit_price ?? $item->product->base_price,
                'unit_cost' => $item->unit_cost,
                'tax_code_id' => $item->orderItem?->tax_code_id ?? $item->product->tax_code_id,
                'note' => $item->note,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, unit_price: float, unit_cost?: ?float, tax_code_id?: ?int, delivery_item_id?: ?int, sales_order_item_id?: ?int, discount_amount?: ?float, note?: ?string}>  $items
     */
    public function save(?SalesInvoice $invoice, array $attributes, array $items): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $attributes, $items): SalesInvoice {
            if ($invoice !== null && ! $invoice->status->isEditable()) {
                throw new RuntimeException('Invoice yang sudah diposting tidak bisa diubah.');
            }

            $customer = Customer::findOrFail($attributes['customer_id']);
            $date = Carbon::parse($attributes['invoice_date']);
            $term = PaymentTermType::from(
                $attributes['payment_term'] ?? $customer->effectivePaymentTerm()->value,
            );

            $invoice ??= new SalesInvoice([
                'status' => DocumentStatus::Draft,
                'created_by' => Auth::id(),
            ]);

            $invoice->fill([
                'customer_id' => $customer->getKey(),
                'sales_order_id' => $attributes['sales_order_id'] ?? $invoice->sales_order_id,
                'delivery_id' => $attributes['delivery_id'] ?? $invoice->delivery_id,
                'cashier_shift_id' => $attributes['cashier_shift_id'] ?? $invoice->cashier_shift_id,
                'warehouse_id' => $attributes['warehouse_id'] ?? $invoice->warehouse_id,
                'branch_id' => $attributes['branch_id'] ?? $invoice->branch_id,
                'invoice_date' => $date->toDateString(),
                'due_date' => $attributes['due_date'] ?? $date->copy()->addDays($term->days())->toDateString(),
                'payment_term' => $term,
                'source' => $attributes['source'] ?? $invoice->source ?? 'manual',
                'is_tax_inclusive' => (bool) ($attributes['is_tax_inclusive'] ?? false),
                'shipping_cost' => (float) ($attributes['shipping_cost'] ?? 0),
                'salesman_id' => $attributes['salesman_id'] ?? $customer->salesman_id ?? Auth::id(),
                'note' => $attributes['note'] ?? null,
            ]);

            $invoice->number ??= $this->numbers->next('sales_invoice', $invoice->branch_id, $date);
            $invoice->save();

            $this->syncItems($invoice, $items);

            return $invoice->refresh();
        });
    }

    /**
     * Posting invoice: piutang terbentuk dan kuantitas yang sudah ditagih
     * dicatat agar satu pengiriman tidak ditagih dua kali.
     */
    public function post(SalesInvoice $invoice, float $paidAmount = 0): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $paidAmount): SalesInvoice {
            if ($invoice->status !== DocumentStatus::Draft) {
                throw new RuntimeException('Hanya invoice draft yang bisa diposting.');
            }

            $invoice->load('items', 'customer');

            if ($invoice->items->isEmpty()) {
                throw new RuntimeException('Invoice tanpa baris tidak bisa diposting.');
            }

            foreach ($invoice->items as $item) {
                $this->applyToSources($item);
            }

            $invoice->paid_amount = round(min($paidAmount, $invoice->total), 4);
            $invoice->outstanding_amount = round($invoice->total - $invoice->paid_amount, 4);
            $invoice->status = DocumentStatus::Posted;
            $invoice->posted_by = Auth::id();
            $invoice->posted_at = now();
            $invoice->save();

            if ($invoice->outstanding_amount > 0) {
                $this->createReceivable($invoice);
            }

            $this->refreshCustomerProfile($invoice);

            return $invoice->refresh();
        });
    }

    public function cancel(SalesInvoice $invoice): SalesInvoice
    {
        if ($invoice->status !== DocumentStatus::Draft) {
            throw new RuntimeException('Invoice yang sudah diposting hanya bisa dikoreksi lewat retur atau reversal.');
        }

        $invoice->status = DocumentStatus::Cancelled;
        $invoice->save();

        return $invoice;
    }

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, unit_price: float, unit_cost?: ?float, tax_code_id?: ?int, delivery_item_id?: ?int, sales_order_item_id?: ?int, discount_amount?: ?float, note?: ?string}>  $items
     */
    private function syncItems(SalesInvoice $invoice, array $items): void
    {
        $invoice->items()->delete();

        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $costTotal = 0.0;

        foreach ($items as $row) {
            $product = Product::findOrFail($row['product_id']);
            $unitId = (int) ($row['unit_id'] ?: $product->base_unit_id);
            $taxCodeId = $row['tax_code_id'] ?? $product->tax_code_id;
            $taxCode = $taxCodeId ? TaxCode::find($taxCodeId) : null;
            $quantity = (float) $row['quantity'];
            $baseQuantity = $product->toBaseQuantity($quantity, $unitId);

            $line = $this->calculator->line(
                quantity: $quantity,
                unitPrice: (float) $row['unit_price'],
                discountAmount: isset($row['discount_amount']) ? (float) $row['discount_amount'] : null,
                taxCode: $taxCode,
                taxInclusive: (bool) $invoice->is_tax_inclusive,
            );

            $unitCost = (float) ($row['unit_cost'] ?? $product->average_cost);

            SalesInvoiceItem::create([
                'company_id' => $invoice->company_id,
                'sales_invoice_id' => $invoice->getKey(),
                'sales_order_item_id' => $row['sales_order_item_id'] ?? null,
                'delivery_item_id' => $row['delivery_item_id'] ?? null,
                'product_id' => $product->getKey(),
                'unit_id' => $unitId,
                'tax_code_id' => $taxCode?->getKey(),
                'quantity' => $quantity,
                'base_quantity' => $baseQuantity,
                'unit_price' => $row['unit_price'],
                'unit_cost' => $unitCost,
                'discount_amount' => $line['discount_amount'],
                'tax_amount' => $line['tax_amount'],
                'line_total' => $line['line_total'],
                'note' => $row['note'] ?? null,
            ]);

            $subtotal += $line['base_amount'];
            $discountTotal += $line['discount_amount'];
            $taxTotal += $line['tax_amount'];
            $costTotal += $baseQuantity * $unitCost;
        }

        $invoice->subtotal = round($subtotal, 4);
        $invoice->discount_amount = round($discountTotal, 4);
        $invoice->tax_amount = round($taxTotal, 4);
        $invoice->cost_of_goods = round($costTotal, 4);
        $invoice->total = round($subtotal + $taxTotal + $invoice->shipping_cost, 4);
        $invoice->outstanding_amount = round($invoice->total - $invoice->paid_amount, 4);
        $invoice->save();
    }

    private function applyToSources(SalesInvoiceItem $item): void
    {
        if ($item->delivery_item_id !== null) {
            $deliveryItem = DeliveryItem::find($item->delivery_item_id);

            if ($deliveryItem !== null) {
                if ($item->base_quantity > $deliveryItem->uninvoicedBaseQuantity() + 0.0001) {
                    throw new RuntimeException(
                        "Jumlah tagihan {$item->product?->sku} melebihi barang yang dikirim dan belum ditagih."
                    );
                }

                $deliveryItem->invoiced_base_quantity = round(
                    $deliveryItem->invoiced_base_quantity + $item->base_quantity,
                    4,
                );
                $deliveryItem->save();
            }
        }

        if ($item->sales_order_item_id === null) {
            return;
        }

        $orderItem = SalesOrderItem::find($item->sales_order_item_id);

        if ($orderItem !== null) {
            $orderItem->invoiced_base_quantity = round(
                $orderItem->invoiced_base_quantity + $item->base_quantity,
                4,
            );
            $orderItem->save();
        }
    }

    private function createReceivable(SalesInvoice $invoice): Receivable
    {
        $receivable = Receivable::firstOrNew([
            'document_type' => 'sales_invoice',
            'document_id' => $invoice->getKey(),
        ]);

        $receivable->company_id = $invoice->company_id;
        $receivable->branch_id = $invoice->branch_id;
        $receivable->customer_id = $invoice->customer_id;
        $receivable->salesman_id = $invoice->salesman_id;
        $receivable->document_number = $invoice->number;
        $receivable->invoice_date = $invoice->invoice_date->toDateString();
        $receivable->due_date = $invoice->due_date->toDateString();
        $receivable->amount = $invoice->total;
        $receivable->paid_amount = $invoice->paid_amount;
        $receivable->refreshStatus()->save();

        return $receivable;
    }

    /**
     * Ringkasan belanja dan piutang customer diperbarui saat invoice diposting
     * supaya kontrol kredit berikutnya memakai angka terkini (PLAN 16).
     */
    private function refreshCustomerProfile(SalesInvoice $invoice): void
    {
        $customer = $invoice->customer;

        if ($customer === null) {
            return;
        }

        $this->credit->refreshCustomer($customer);

        $profile = CustomerCreditProfile::firstOrNew(['customer_id' => $customer->getKey()]);

        $aggregate = SalesInvoice::query()
            ->where('customer_id', $customer->getKey())
            ->where('status', DocumentStatus::Posted)
            ->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(total), 0) as total')
            ->first();

        $profile->company_id = $customer->company_id;
        $profile->total_purchase = round((float) $aggregate->total, 4);
        $profile->order_count = (int) $aggregate->invoice_count;
        $profile->average_invoice = $aggregate->invoice_count > 0
            ? round((float) $aggregate->total / (int) $aggregate->invoice_count, 4)
            : 0.0;
        $profile->last_order_date = $invoice->invoice_date->toDateString();
        $profile->is_dormant = false;
        $profile->save();
    }
}
