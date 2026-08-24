<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Livewire;

use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Core\Services\LineCalculator;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use App\Modules\Purchase\Models\GoodsReceipt;
use App\Modules\Purchase\Models\PurchaseInvoice;
use App\Modules\Purchase\Models\PurchaseInvoiceItem;
use App\Modules\Purchase\Services\PurchaseInvoiceService;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Support\Carbon;
use Livewire\Component;
use RuntimeException;

class InvoiceForm extends Component
{
    public ?int $invoiceId = null;

    public array $form = [
        'goods_receipt_id' => null,
        'purchase_order_id' => null,
        'supplier_id' => null,
        'branch_id' => null,
        'supplier_invoice_number' => '',
        'invoice_date' => '',
        'due_date' => null,
        'payment_term' => PaymentTermType::Net30->value,
        'other_cost' => 0,
        'note' => '',
    ];

    /** @var array<int, array{goods_receipt_item_id: ?int, purchase_order_item_id: ?int, product_id: ?int, unit_id: ?int, quantity: float, unit_price: ?float, tax_code_id: ?int, discount_amount: ?float, note: ?string}> */
    public array $items = [];

    public function mount(?PurchaseInvoice $invoice = null, ?int $receiptId = null): void
    {
        if ($invoice?->exists) {
            $this->invoiceId = $invoice->getKey();
            $this->form = [
                'goods_receipt_id' => null,
                'purchase_order_id' => $invoice->purchase_order_id,
                'supplier_id' => $invoice->supplier_id,
                'branch_id' => $invoice->branch_id,
                'supplier_invoice_number' => $invoice->supplier_invoice_number,
                'invoice_date' => $invoice->invoice_date->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'payment_term' => $invoice->payment_term->value,
                'other_cost' => $invoice->other_cost,
                'note' => $invoice->note,
            ];

            $this->items = $invoice->items
                ->map(fn (PurchaseInvoiceItem $item) => [
                    'goods_receipt_item_id' => $item->goods_receipt_item_id,
                    'purchase_order_item_id' => $item->purchase_order_item_id,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_code_id' => $item->tax_code_id,
                    'discount_amount' => $item->discount_amount,
                    'note' => $item->note,
                ])->all();

            return;
        }

        $this->form['invoice_date'] = now()->toDateString();

        if ($receiptId !== null) {
            $this->loadReceipt($receiptId);

            return;
        }

        $this->addItemRow();
    }

    public function updatedForm(mixed $value, string $key): void
    {
        if ($key === 'goods_receipt_id' && $value) {
            $this->loadReceipt((int) $value);
        }

        if ($key === 'payment_term') {
            $this->refreshDueDate();
        }

        if ($key === 'invoice_date') {
            $this->refreshDueDate();
        }
    }

    public function addItemRow(): void
    {
        $this->items[] = [
            'goods_receipt_item_id' => null,
            'purchase_order_item_id' => null,
            'product_id' => null,
            'unit_id' => null,
            'quantity' => 1,
            'unit_price' => null,
            'tax_code_id' => null,
            'discount_amount' => null,
            'note' => null,
        ];
    }

    public function removeItemRow(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function updatedItems(mixed $value, string $key): void
    {
        if (! str_ends_with($key, '.product_id') || ! $value) {
            return;
        }

        $index = (int) explode('.', $key)[0];
        $product = Product::find($value);

        if ($product === null) {
            return;
        }

        $this->items[$index]['unit_id'] = $this->items[$index]['unit_id'] ?: $product->base_unit_id;
        $this->items[$index]['unit_price'] = $product->last_purchase_cost ?: $product->average_cost;
        $this->items[$index]['tax_code_id'] = $product->tax_code_id;
    }

    public function save(bool $andPost = false): void
    {
        $validated = $this->validate([
            'form.purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'form.supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'form.branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'form.supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'form.invoice_date' => ['required', 'date'],
            'form.due_date' => ['nullable', 'date', 'after_or_equal:form.invoice_date'],
            'form.payment_term' => ['required', 'in:'.implode(',', array_keys(PaymentTermType::options()))],
            'form.other_cost' => ['nullable', 'numeric'],
            'form.note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.goods_receipt_item_id' => ['nullable', 'integer', 'exists:goods_receipt_items,id'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_code_id' => ['nullable', 'integer', 'exists:tax_codes,id'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ], [
            'form.due_date.after_or_equal' => 'Jatuh tempo tidak boleh sebelum tanggal invoice.',
            'items.required' => 'Minimal satu baris tagihan.',
        ]);

        try {
            $invoices = app(PurchaseInvoiceService::class);

            $invoice = $invoices->save(
                $this->invoiceId ? PurchaseInvoice::findOrFail($this->invoiceId) : null,
                $validated['form'],
                $validated['items'],
            );

            if ($andPost) {
                $invoices->post($invoice);
            }
        } catch (RuntimeException $exception) {
            $this->addError('items', $exception->getMessage());

            return;
        }

        session()->flash('status', $andPost
            ? 'Invoice diposting dan hutang supplier terbentuk.'
            : 'Invoice pembelian disimpan sebagai draft.');

        $this->redirectRoute('purchase.invoices.detail', $invoice, navigate: true);
    }

    public function render()
    {
        return view('purchase.livewire.invoice-form', [
            'receipts' => $this->uninvoicedReceipts(),
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'products' => Product::active()->orderBy('name')->limit(500)->get(),
            'units' => Unit::where('is_active', true)->orderBy('code')->pluck('code', 'id'),
            'taxCodes' => TaxCode::where('is_active', true)->where('for_purchase', true)->orderBy('code')->get(),
            'paymentTerms' => PaymentTermType::options(),
            'summary' => $this->summary(),
        ]);
    }

    /**
     * @return array{subtotal: float, discount: float, tax: float, total: float}
     */
    private function summary(): array
    {
        $calculator = app(LineCalculator::class);
        $taxCodes = TaxCode::whereIn('id', array_filter(array_column($this->items, 'tax_code_id')))
            ->get()
            ->keyBy('id');

        $subtotal = 0.0;
        $discount = 0.0;
        $tax = 0.0;

        foreach ($this->items as $row) {
            $line = $calculator->line(
                quantity: (float) ($row['quantity'] ?? 0),
                unitPrice: (float) ($row['unit_price'] ?? 0),
                discountAmount: isset($row['discount_amount']) ? (float) $row['discount_amount'] : null,
                taxCode: $row['tax_code_id'] ? $taxCodes->get($row['tax_code_id']) : null,
            );

            $subtotal += $line['base_amount'];
            $discount += $line['discount_amount'];
            $tax += $line['tax_amount'];
        }

        return [
            'subtotal' => round($subtotal, 4),
            'discount' => round($discount, 4),
            'tax' => round($tax, 4),
            'total' => round($subtotal + $tax + (float) ($this->form['other_cost'] ?? 0), 4),
        ];
    }

    /**
     * Tagihan supplier hanya boleh bersandar pada barang yang sudah diterima
     * dan belum ditagih, sehingga hutang tidak pernah dobel (PLAN 22).
     */
    private function loadReceipt(int $receiptId): void
    {
        $receipt = GoodsReceipt::with('items.product', 'items.orderItem', 'order')->find($receiptId);

        if ($receipt === null || ! $receipt->isPosted()) {
            $this->addError('form.goods_receipt_id', 'Penerimaan ini belum diposting.');

            return;
        }

        $this->form['goods_receipt_id'] = $receipt->getKey();
        $this->form['supplier_id'] = $receipt->supplier_id;
        $this->form['branch_id'] = $receipt->branch_id;
        $this->form['purchase_order_id'] = $receipt->purchase_order_id;
        $this->form['payment_term'] = ($receipt->order?->payment_term
            ?? $receipt->supplier?->payment_term
            ?? PaymentTermType::Net30)->value;

        $this->items = app(PurchaseInvoiceService::class)->draftItemsFromReceipt($receipt);
        $this->refreshDueDate();

        if ($this->items === []) {
            $this->addError('form.goods_receipt_id', 'Seluruh barang pada penerimaan ini sudah ditagih.');
            $this->addItemRow();
        }
    }

    private function uninvoicedReceipts()
    {
        return GoodsReceipt::query()
            ->where('status', DocumentStatus::Posted)
            ->whereHas('items', fn ($query) => $query
                ->whereRaw('invoiced_base_quantity < base_quantity - rejected_base_quantity'))
            ->with('supplier')
            ->orderByDesc('receipt_date')
            ->limit(200)
            ->get();
    }

    private function refreshDueDate(): void
    {
        $term = PaymentTermType::tryFrom((string) $this->form['payment_term']) ?? PaymentTermType::Net30;

        $this->form['due_date'] = Carbon::parse($this->form['invoice_date'] ?: now()->toDateString())
            ->addDays($term->days())
            ->toDateString();
    }
}
