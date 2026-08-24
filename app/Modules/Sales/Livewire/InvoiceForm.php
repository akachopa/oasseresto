<?php

declare(strict_types=1);

namespace App\Modules\Sales\Livewire;

use App\Modules\Core\Enums\DeliveryStatus;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Customer\Models\Customer;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use App\Modules\Sales\Services\SalesInvoiceService;
use App\Modules\Sales\Services\SalesLineSummary;
use Illuminate\Support\Carbon;
use Livewire\Component;
use RuntimeException;

class InvoiceForm extends Component
{
    public ?int $invoiceId = null;

    public array $form = [
        'delivery_id' => null,
        'sales_order_id' => null,
        'customer_id' => null,
        'warehouse_id' => null,
        'branch_id' => null,
        'invoice_date' => '',
        'due_date' => null,
        'payment_term' => PaymentTermType::Net30->value,
        'is_tax_inclusive' => false,
        'shipping_cost' => 0,
        'note' => '',
    ];

    public float $paidAmount = 0;

    /** @var array<int, array{delivery_item_id: ?int, sales_order_item_id: ?int, product_id: ?int, unit_id: ?int, quantity: float, unit_price: ?float, unit_cost: ?float, tax_code_id: ?int, discount_amount: ?float, note: ?string}> */
    public array $items = [];

    public function mount(?SalesInvoice $invoice = null, ?int $deliveryId = null): void
    {
        if ($invoice?->exists) {
            $this->invoiceId = $invoice->getKey();
            $this->form = [
                'delivery_id' => $invoice->delivery_id,
                'sales_order_id' => $invoice->sales_order_id,
                'customer_id' => $invoice->customer_id,
                'warehouse_id' => $invoice->warehouse_id,
                'branch_id' => $invoice->branch_id,
                'invoice_date' => $invoice->invoice_date->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'payment_term' => $invoice->payment_term->value,
                'is_tax_inclusive' => (bool) $invoice->is_tax_inclusive,
                'shipping_cost' => $invoice->shipping_cost,
                'note' => $invoice->note,
            ];

            $this->items = $invoice->items
                ->map(fn (SalesInvoiceItem $item) => [
                    'delivery_item_id' => $item->delivery_item_id,
                    'sales_order_item_id' => $item->sales_order_item_id,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'unit_cost' => $item->unit_cost,
                    'tax_code_id' => $item->tax_code_id,
                    'discount_amount' => $item->discount_amount,
                    'note' => $item->note,
                ])->all();

            return;
        }

        $this->form['invoice_date'] = now()->toDateString();

        if ($deliveryId !== null) {
            $this->loadDelivery($deliveryId);

            return;
        }

        $this->refreshDueDate();
        $this->addItemRow();
    }

    public function updatedForm(mixed $value, string $key): void
    {
        if ($key === 'delivery_id' && $value) {
            $this->loadDelivery((int) $value);
        }

        if (in_array($key, ['payment_term', 'invoice_date'], true)) {
            $this->refreshDueDate();
        }

        if ($key === 'customer_id' && $value) {
            $customer = Customer::find($value);

            if ($customer !== null) {
                $this->form['payment_term'] = $customer->effectivePaymentTerm()->value;
                $this->refreshDueDate();
            }
        }
    }

    public function addItemRow(): void
    {
        $this->items[] = [
            'delivery_item_id' => null,
            'sales_order_item_id' => null,
            'product_id' => null,
            'unit_id' => null,
            'quantity' => 1,
            'unit_price' => null,
            'unit_cost' => null,
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

        $unitId = (int) ($this->items[$index]['unit_id'] ?: ($product->sales_unit_id ?? $product->base_unit_id));

        $this->items[$index]['unit_id'] = $unitId;
        $this->items[$index]['unit_price'] = round((float) $product->base_price * $product->conversionFor($unitId), 4);
        $this->items[$index]['unit_cost'] = round((float) $product->average_cost * $product->conversionFor($unitId), 4);
        $this->items[$index]['tax_code_id'] = $product->tax_code_id;
    }

    public function save(bool $andPost = false): void
    {
        $validated = $this->validate([
            'form.delivery_id' => ['nullable', 'integer', 'exists:deliveries,id'],
            'form.sales_order_id' => ['nullable', 'integer', 'exists:sales_orders,id'],
            'form.customer_id' => ['required', 'integer', 'exists:customers,id'],
            'form.warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'form.branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'form.invoice_date' => ['required', 'date'],
            'form.due_date' => ['nullable', 'date', 'after_or_equal:form.invoice_date'],
            'form.payment_term' => ['required', 'in:'.implode(',', array_keys(PaymentTermType::options()))],
            'form.is_tax_inclusive' => ['boolean'],
            'form.shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'form.note' => ['nullable', 'string'],
            'paidAmount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.delivery_item_id' => ['nullable', 'integer', 'exists:delivery_items,id'],
            'items.*.sales_order_item_id' => ['nullable', 'integer', 'exists:sales_order_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_code_id' => ['nullable', 'integer', 'exists:tax_codes,id'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ], [
            'form.due_date.after_or_equal' => 'Jatuh tempo tidak boleh sebelum tanggal invoice.',
            'items.required' => 'Minimal satu baris tagihan.',
        ]);

        try {
            $invoices = app(SalesInvoiceService::class);

            $invoice = $invoices->save(
                $this->invoiceId ? SalesInvoice::findOrFail($this->invoiceId) : null,
                $validated['form'],
                $validated['items'],
            );

            if ($andPost) {
                $invoices->post($invoice, (float) $this->paidAmount);
            }
        } catch (RuntimeException $exception) {
            $this->addError('items', $exception->getMessage());

            return;
        }

        session()->flash('status', $andPost
            ? 'Invoice diposting dan piutang customer terbentuk.'
            : 'Invoice penjualan disimpan sebagai draft.');

        $this->redirectRoute('sales.invoices.detail', $invoice, navigate: true);
    }

    public function render()
    {
        return view('sales.livewire.invoice-form', [
            'deliveries' => $this->uninvoicedDeliveries(),
            'customers' => Customer::active()->orderBy('name')->get(),
            'products' => Product::active()->orderBy('name')->limit(500)->get(),
            'units' => Unit::where('is_active', true)->orderBy('code')->pluck('code', 'id'),
            'taxCodes' => TaxCode::where('is_active', true)->where('for_sales', true)->orderBy('code')->get(),
            'paymentTerms' => PaymentTermType::options(),
            'summary' => app(SalesLineSummary::class)->of(
                $this->items,
                (bool) $this->form['is_tax_inclusive'],
                (float) ($this->form['shipping_cost'] ?? 0),
            ),
        ]);
    }

    /**
     * Tagihan hanya boleh bersandar pada barang yang sudah keluar dan belum
     * ditagih, sehingga piutang tidak pernah dobel (PLAN 29).
     */
    private function loadDelivery(int $deliveryId): void
    {
        $delivery = Delivery::with('items.product', 'items.orderItem', 'order', 'customer')->find($deliveryId);

        if ($delivery === null || $delivery->uninvoicedBaseQuantity() <= 0) {
            $this->addError('form.delivery_id', 'Surat jalan ini belum dikirim atau sudah ditagih penuh.');

            return;
        }

        $this->form['delivery_id'] = $delivery->getKey();
        $this->form['sales_order_id'] = $delivery->sales_order_id;
        $this->form['customer_id'] = $delivery->customer_id;
        $this->form['warehouse_id'] = $delivery->warehouse_id;
        $this->form['branch_id'] = $delivery->branch_id;
        $this->form['payment_term'] = ($delivery->order?->payment_term
            ?? $delivery->customer?->effectivePaymentTerm()
            ?? PaymentTermType::Net30)->value;

        $this->items = app(SalesInvoiceService::class)->draftItemsFromDelivery($delivery);
        $this->refreshDueDate();

        if ($this->items === []) {
            $this->addItemRow();
        }
    }

    private function uninvoicedDeliveries()
    {
        return Delivery::query()
            ->whereIn('status', [DeliveryStatus::Dispatched, DeliveryStatus::Delivered])
            ->whereHas('items', fn ($query) => $query
                ->whereRaw('invoiced_base_quantity < picked_base_quantity'))
            ->with('customer')
            ->orderByDesc('delivery_date')
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
