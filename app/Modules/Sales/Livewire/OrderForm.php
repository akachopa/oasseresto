<?php

declare(strict_types=1);

namespace App\Modules\Sales\Livewire;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use App\Modules\Product\Services\MarginGuard;
use App\Modules\Product\Services\PricingService;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderItem;
use App\Modules\Sales\Services\CreditControlService;
use App\Modules\Sales\Services\SalesLineSummary;
use App\Modules\Sales\Services\SalesOrderService;
use Livewire\Component;
use RuntimeException;

class OrderForm extends Component
{
    public ?int $orderId = null;

    public array $form = [
        'customer_id' => null,
        'customer_address_id' => null,
        'warehouse_id' => null,
        'order_date' => '',
        'requested_delivery_date' => null,
        'payment_term' => PaymentTermType::Net30->value,
        'is_tax_inclusive' => false,
        'shipping_cost' => 0,
        'note' => '',
    ];

    /** @var array<int, array{product_id: ?int, unit_id: ?int, quantity: float, unit_price: ?float, discount_percent: ?float, tax_code_id: ?int, note: ?string}> */
    public array $items = [];

    public function mount(?SalesOrder $order = null, ?int $customerId = null): void
    {
        if ($order?->exists) {
            $this->orderId = $order->getKey();
            $this->form = [
                'customer_id' => $order->customer_id,
                'customer_address_id' => $order->customer_address_id,
                'warehouse_id' => $order->warehouse_id,
                'order_date' => $order->order_date->toDateString(),
                'requested_delivery_date' => $order->requested_delivery_date?->toDateString(),
                'payment_term' => $order->payment_term->value,
                'is_tax_inclusive' => (bool) $order->is_tax_inclusive,
                'shipping_cost' => $order->shipping_cost,
                'note' => $order->note,
            ];

            $this->items = $order->items
                ->map(fn (SalesOrderItem $item) => [
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'tax_code_id' => $item->tax_code_id,
                    'note' => $item->note,
                ])->all();

            return;
        }

        $this->form['order_date'] = now()->toDateString();
        $this->form['requested_delivery_date'] = now()->addDay()->toDateString();
        $this->form['warehouse_id'] = $this->defaultWarehouseId();
        $this->form['customer_id'] = $customerId;
        $this->applyCustomerDefaults();
        $this->addItemRow();
    }

    public function updatedForm(mixed $value, string $key): void
    {
        if ($key === 'customer_id') {
            $this->applyCustomerDefaults();
            $this->repriceItems();
        }
    }

    public function addItemRow(): void
    {
        $this->items[] = [
            'product_id' => null,
            'unit_id' => null,
            'quantity' => 1,
            'unit_price' => null,
            'discount_percent' => 0,
            'tax_code_id' => null,
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
        $this->applyPrice($index);
    }

    public function save(bool $andSubmit = false): void
    {
        $validated = $this->validate([
            'form.customer_id' => ['required', 'integer', 'exists:customers,id'],
            'form.customer_address_id' => ['nullable', 'integer', 'exists:customer_addresses,id'],
            'form.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'form.order_date' => ['required', 'date'],
            'form.requested_delivery_date' => ['nullable', 'date', 'after_or_equal:form.order_date'],
            'form.payment_term' => ['required', 'in:'.implode(',', array_keys(PaymentTermType::options()))],
            'form.is_tax_inclusive' => ['boolean'],
            'form.shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'form.note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'items.*.tax_code_id' => ['nullable', 'integer', 'exists:tax_codes,id'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ], [
            'form.requested_delivery_date.after_or_equal' => 'Tanggal kirim tidak boleh sebelum tanggal order.',
            'items.required' => 'Minimal satu baris barang.',
        ]);

        try {
            $orders = app(SalesOrderService::class);

            $order = $orders->save(
                $this->orderId ? SalesOrder::findOrFail($this->orderId) : null,
                $validated['form'],
                $validated['items'],
            );

            if ($andSubmit) {
                $orders->submit($order);
            }
        } catch (RuntimeException $exception) {
            $this->addError('items', $exception->getMessage());

            return;
        }

        session()->flash('status', $andSubmit
            ? 'Sales order diajukan.'
            : 'Sales order disimpan sebagai draft.');

        $this->redirectRoute('sales.orders.detail', $order, navigate: true);
    }

    public function render()
    {
        $customer = $this->form['customer_id'] ? Customer::find($this->form['customer_id']) : null;
        $summary = app(SalesLineSummary::class)->of(
            $this->items,
            (bool) $this->form['is_tax_inclusive'],
            (float) ($this->form['shipping_cost'] ?? 0),
        );

        return view('sales.livewire.order-form', [
            'customers' => Customer::active()->orderBy('name')->get(),
            'addresses' => $customer?->addresses()->orderByDesc('is_default')->get() ?? collect(),
            'warehouses' => Warehouse::active()->orderBy('name')->get(),
            'products' => Product::active()->stocked()->orderBy('name')->limit(500)->get(),
            'units' => Unit::where('is_active', true)->orderBy('code')->pluck('code', 'id'),
            'taxCodes' => TaxCode::where('is_active', true)->where('for_sales', true)->orderBy('code')->get(),
            'paymentTerms' => PaymentTermType::options(),
            'summary' => $summary,
            'availability' => $this->availability(),
            'margins' => $this->margins(),
            'credit' => $customer
                ? app(CreditControlService::class)->evaluate(
                    $customer,
                    $summary['total'],
                    PaymentTermType::tryFrom((string) $this->form['payment_term']),
                    $this->orderId,
                )
                : null,
        ]);
    }

    /**
     * Sisa stok yang benar-benar bisa dijanjikan, sudah dikurangi reservasi
     * order lain, supaya salesman tidak menjanjikan barang yang sama dua kali.
     *
     * @return array<int, float>
     */
    private function availability(): array
    {
        if (! $this->form['warehouse_id']) {
            return [];
        }

        $stock = app(StockService::class);

        return collect($this->items)
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->mapWithKeys(fn ($productId) => [
                (int) $productId => $stock->available((int) $productId, (int) $this->form['warehouse_id']),
            ])
            ->all();
    }

    /**
     * @return array<int, array{margin_percent: float, minimum: float, requires_approval: bool, message: ?string}>
     */
    private function margins(): array
    {
        $guard = app(MarginGuard::class);
        $margins = [];

        foreach ($this->items as $index => $row) {
            if (! $row['product_id'] || ! $row['unit_price']) {
                continue;
            }

            $product = Product::find($row['product_id']);

            if ($product === null) {
                continue;
            }

            $unitId = (int) ($row['unit_id'] ?: $product->base_unit_id);
            $unitCost = round((float) $product->average_cost * $product->conversionFor($unitId), 4);
            $price = (float) $row['unit_price'] * (1 - (float) ($row['discount_percent'] ?? 0) / 100);

            $verdict = $guard->evaluate($product, $price, $unitCost);

            $margins[$index] = [
                'margin_percent' => $verdict['margin_percent'],
                'minimum' => $verdict['minimum'],
                'requires_approval' => $verdict['requires_approval'] || ! $verdict['allowed'],
                'message' => $verdict['message'],
            ];
        }

        return $margins;
    }

    private function applyCustomerDefaults(): void
    {
        if (! $this->form['customer_id']) {
            return;
        }

        $customer = Customer::find($this->form['customer_id']);

        if ($customer !== null) {
            $this->form['payment_term'] = $customer->effectivePaymentTerm()->value;
            $this->form['customer_address_id'] = $customer->addresses()
                ->orderByDesc('is_default')
                ->value('id');
        }
    }

    private function repriceItems(): void
    {
        foreach (array_keys($this->items) as $index) {
            if ($this->items[$index]['product_id']) {
                $this->applyPrice($index);
            }
        }
    }

    private function applyPrice(int $index): void
    {
        $product = Product::find($this->items[$index]['product_id']);

        if ($product === null) {
            return;
        }

        $unitId = (int) ($this->items[$index]['unit_id'] ?: ($product->sales_unit_id ?? $product->base_unit_id));

        $quote = app(PricingService::class)->quote(
            product: $product,
            quantity: (float) ($this->items[$index]['quantity'] ?: 1),
            customer: $this->form['customer_id'] ? Customer::find($this->form['customer_id']) : null,
            unitId: $unitId,
            branchId: null,
            paymentTerm: PaymentTermType::tryFrom((string) $this->form['payment_term']),
        );

        $this->items[$index]['unit_id'] = $unitId;
        $this->items[$index]['unit_price'] = $quote->price;
        $this->items[$index]['tax_code_id'] = $product->tax_code_id;
    }

    private function defaultWarehouseId(): ?int
    {
        $branchId = app(ScopeManager::class)->branchId();

        return Warehouse::active()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderByDesc('is_default')
            ->value('id');
    }
}
