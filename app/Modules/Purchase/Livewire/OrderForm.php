<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Livewire;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Core\Services\LineCalculator;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Models\PurchaseOrderItem;
use App\Modules\Purchase\Services\PurchaseOrderService;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Supplier\Models\SupplierProduct;
use Livewire\Component;
use RuntimeException;

class OrderForm extends Component
{
    public ?int $orderId = null;

    public array $form = [
        'supplier_id' => null,
        'warehouse_id' => null,
        'order_date' => '',
        'expected_date' => null,
        'payment_term' => PaymentTermType::Net30->value,
        'is_tax_inclusive' => false,
        'other_cost' => 0,
        'note' => '',
        'terms' => '',
    ];

    /** @var array<int, array{product_id: ?int, unit_id: ?int, quantity: float, unit_price: ?float, discount_percent: ?float, tax_code_id: ?int, purchase_request_item_id: ?int, note: ?string}> */
    public array $items = [];

    public function mount(?PurchaseOrder $order = null, ?int $supplierId = null): void
    {
        if ($order?->exists) {
            $this->orderId = $order->getKey();
            $this->form = [
                'supplier_id' => $order->supplier_id,
                'warehouse_id' => $order->warehouse_id,
                'order_date' => $order->order_date->toDateString(),
                'expected_date' => $order->expected_date?->toDateString(),
                'payment_term' => $order->payment_term->value,
                'is_tax_inclusive' => (bool) $order->is_tax_inclusive,
                'other_cost' => $order->other_cost,
                'note' => $order->note,
                'terms' => $order->terms,
            ];

            $this->items = $order->items
                ->map(fn (PurchaseOrderItem $item) => [
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'tax_code_id' => $item->tax_code_id,
                    'purchase_request_item_id' => $item->purchase_request_item_id,
                    'note' => $item->note,
                ])->all();

            return;
        }

        $this->form['order_date'] = now()->toDateString();
        $this->form['warehouse_id'] = $this->defaultWarehouseId();
        $this->form['supplier_id'] = $supplierId;
        $this->applySupplierDefaults();
        $this->addItemRow();
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
            'purchase_request_item_id' => null,
            'note' => null,
        ];
    }

    public function removeItemRow(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function updatedForm(mixed $value, string $key): void
    {
        if ($key === 'supplier_id') {
            $this->applySupplierDefaults();
        }
    }

    /**
     * Harga diambil dari riwayat harga supplier terkait bila ada, karena
     * harga supplier lebih relevan daripada rata-rata harga beli (PLAN 21).
     */
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

        $link = $this->form['supplier_id']
            ? SupplierProduct::where('supplier_id', $this->form['supplier_id'])
                ->where('product_id', $product->getKey())
                ->first()
            : null;

        $this->items[$index]['unit_id'] = $this->items[$index]['unit_id']
            ?: ($link?->unit_id ?: $product->base_unit_id);
        $this->items[$index]['unit_price'] = $link?->last_price
            ?: ($product->last_purchase_cost ?: $product->average_cost);
        $this->items[$index]['tax_code_id'] = $product->tax_code_id;
    }

    public function save(bool $andSubmit = false): void
    {
        $validated = $this->validate([
            'form.supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'form.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'form.order_date' => ['required', 'date'],
            'form.expected_date' => ['nullable', 'date', 'after_or_equal:form.order_date'],
            'form.payment_term' => ['required', 'in:'.implode(',', array_keys(PaymentTermType::options()))],
            'form.is_tax_inclusive' => ['boolean'],
            'form.other_cost' => ['nullable', 'numeric', 'min:0'],
            'form.note' => ['nullable', 'string'],
            'form.terms' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'items.*.tax_code_id' => ['nullable', 'integer', 'exists:tax_codes,id'],
            'items.*.purchase_request_item_id' => ['nullable', 'integer', 'exists:purchase_request_items,id'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ], [
            'form.expected_date.after_or_equal' => 'Perkiraan kedatangan tidak boleh sebelum tanggal PO.',
            'items.required' => 'Minimal satu baris barang.',
        ]);

        try {
            $orders = app(PurchaseOrderService::class);

            $order = $orders->save(
                $this->orderId ? PurchaseOrder::findOrFail($this->orderId) : null,
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
            ? 'Purchase order diajukan; supplier baru boleh dihubungi setelah disetujui.'
            : 'Purchase order disimpan sebagai draft.');

        $this->redirectRoute('purchase.orders.detail', $order, navigate: true);
    }

    public function render()
    {
        return view('purchase.livewire.order-form', [
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'warehouses' => Warehouse::active()->orderBy('name')->get(),
            'products' => Product::active()->orderBy('name')->limit(500)->get(),
            'units' => Unit::where('is_active', true)->orderBy('code')->pluck('code', 'id'),
            'taxCodes' => TaxCode::where('is_active', true)->where('for_purchase', true)->orderBy('code')->get(),
            'paymentTerms' => PaymentTermType::options(),
            'summary' => $this->summary(),
        ]);
    }

    /**
     * Ringkasan dihitung dengan LineCalculator yang sama seperti saat simpan,
     * sehingga angka di layar tidak pernah beda dengan angka tersimpan.
     *
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
                discountPercent: (float) ($row['discount_percent'] ?? 0),
                taxCode: $row['tax_code_id'] ? $taxCodes->get($row['tax_code_id']) : null,
                taxInclusive: (bool) $this->form['is_tax_inclusive'],
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

    private function applySupplierDefaults(): void
    {
        if (! $this->form['supplier_id']) {
            return;
        }

        $supplier = Supplier::find($this->form['supplier_id']);

        if ($supplier === null) {
            return;
        }

        $this->form['payment_term'] = ($supplier->payment_term ?? PaymentTermType::Net30)->value;
        $this->form['expected_date'] = now()
            ->addDays((int) $supplier->lead_time_days)
            ->toDateString();
    }

    private function defaultWarehouseId(): ?int
    {
        $branchId = app(ScopeManager::class)->branchId();

        return Warehouse::active()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('is_default')
            ->value('id');
    }
}
