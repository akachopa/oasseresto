<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Livewire;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use App\Modules\Purchase\Models\GoodsReceipt;
use App\Modules\Purchase\Models\GoodsReceiptItem;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Services\GoodsReceiptService;
use App\Modules\Supplier\Models\Supplier;
use Livewire\Component;
use RuntimeException;

class ReceiptForm extends Component
{
    public ?int $receiptId = null;

    public array $form = [
        'purchase_order_id' => null,
        'supplier_id' => null,
        'warehouse_id' => null,
        'receipt_date' => '',
        'supplier_do_number' => '',
        'note' => '',
    ];

    /** @var array<int, array{purchase_order_item_id: ?int, product_id: ?int, unit_id: ?int, quantity: float, rejected_base_quantity: ?float, unit_cost: ?float, batch_number: ?string, expiry_date: ?string, note: ?string}> */
    public array $items = [];

    public function mount(?GoodsReceipt $receipt = null, ?int $orderId = null): void
    {
        if ($receipt?->exists) {
            $this->receiptId = $receipt->getKey();
            $this->form = [
                'purchase_order_id' => $receipt->purchase_order_id,
                'supplier_id' => $receipt->supplier_id,
                'warehouse_id' => $receipt->warehouse_id,
                'receipt_date' => $receipt->receipt_date->toDateString(),
                'supplier_do_number' => $receipt->supplier_do_number,
                'note' => $receipt->note,
            ];

            $this->items = $receipt->items
                ->map(fn (GoodsReceiptItem $item) => [
                    'purchase_order_item_id' => $item->purchase_order_item_id,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'rejected_base_quantity' => $item->rejected_base_quantity,
                    'unit_cost' => $item->unit_cost,
                    'batch_number' => $item->batch_number,
                    'expiry_date' => $item->expiry_date?->toDateString(),
                    'note' => $item->note,
                ])->all();

            return;
        }

        $this->form['receipt_date'] = now()->toDateString();
        $this->form['warehouse_id'] = $this->defaultWarehouseId();

        if ($orderId !== null) {
            $this->loadOrder($orderId);

            return;
        }

        $this->addItemRow();
    }

    public function updatedForm(mixed $value, string $key): void
    {
        if ($key === 'purchase_order_id' && $value) {
            $this->loadOrder((int) $value);
        }
    }

    public function addItemRow(): void
    {
        $this->items[] = [
            'purchase_order_item_id' => null,
            'product_id' => null,
            'unit_id' => null,
            'quantity' => 1,
            'rejected_base_quantity' => 0,
            'unit_cost' => null,
            'batch_number' => null,
            'expiry_date' => null,
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
        $this->items[$index]['unit_cost'] = $product->last_purchase_cost ?: $product->average_cost;
    }

    public function save(bool $andPost = false): void
    {
        $validated = $this->validate([
            'form.purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'form.supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'form.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'form.receipt_date' => ['required', 'date'],
            'form.supplier_do_number' => ['nullable', 'string', 'max:100'],
            'form.note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.rejected_base_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.batch_number' => ['nullable', 'string', 'max:100'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ], [
            'items.required' => 'Minimal satu baris barang.',
        ]);

        try {
            $receipts = app(GoodsReceiptService::class);

            $receipt = $receipts->save(
                $this->receiptId ? GoodsReceipt::findOrFail($this->receiptId) : null,
                $validated['form'],
                $validated['items'],
            );

            if ($andPost) {
                $receipts->post($receipt);
            }
        } catch (RuntimeException $exception) {
            $this->addError('items', $exception->getMessage());

            return;
        }

        session()->flash('status', $andPost
            ? 'Penerimaan diposting; batch terbentuk dan stok gudang bertambah.'
            : 'Penerimaan disimpan sebagai draft.');

        $this->redirectRoute('purchase.receipts.detail', $receipt, navigate: true);
    }

    public function render()
    {
        return view('purchase.livewire.receipt-form', [
            'orders' => $this->openOrders(),
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'warehouses' => Warehouse::active()->orderBy('name')->get(),
            'products' => Product::active()->stocked()->orderBy('name')->limit(500)->get(),
            'units' => Unit::where('is_active', true)->orderBy('code')->pluck('code', 'id'),
            'outstanding' => $this->outstandingByOrderItem(),
        ]);
    }

    /**
     * Baris penerimaan disiapkan dari sisa PO, jadi petugas gudang hanya
     * mengoreksi bila ada kekurangan kirim atau barang rusak.
     */
    private function loadOrder(int $orderId): void
    {
        $order = PurchaseOrder::with('items.product')->find($orderId);

        if ($order === null || ! $order->isReceivable()) {
            $this->addError('form.purchase_order_id', 'Purchase order ini tidak bisa diterima.');

            return;
        }

        $this->form['purchase_order_id'] = $order->getKey();
        $this->form['supplier_id'] = $order->supplier_id;
        $this->form['warehouse_id'] = $order->warehouse_id;
        $this->items = app(GoodsReceiptService::class)->draftItemsFromOrder($order);

        if ($this->items === []) {
            $this->addError('form.purchase_order_id', 'Seluruh barang pada purchase order ini sudah diterima.');
            $this->addItemRow();
        }
    }

    /**
     * @return array<int, float>
     */
    private function outstandingByOrderItem(): array
    {
        if (! $this->form['purchase_order_id']) {
            return [];
        }

        $order = PurchaseOrder::with('items')->find($this->form['purchase_order_id']);

        return $order === null
            ? []
            : $order->items
                ->mapWithKeys(fn ($item) => [$item->getKey() => $item->outstandingBaseQuantity()])
                ->all();
    }

    private function openOrders()
    {
        return PurchaseOrder::query()
            ->whereIn('status', [DocumentStatus::Approved, DocumentStatus::PartiallyProcessed])
            ->with('supplier')
            ->orderByDesc('order_date')
            ->limit(200)
            ->get();
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
