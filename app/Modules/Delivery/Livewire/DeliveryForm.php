<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Livewire;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliveryItem;
use App\Modules\Delivery\Services\DeliveryService;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use App\Modules\Sales\Models\SalesOrder;
use Livewire\Component;
use RuntimeException;

class DeliveryForm extends Component
{
    public ?int $deliveryId = null;

    public array $form = [
        'sales_order_id' => null,
        'customer_id' => null,
        'customer_address_id' => null,
        'warehouse_id' => null,
        'delivery_date' => '',
        'shipping_address' => '',
        'driver_name' => '',
        'vehicle_number' => '',
        'note' => '',
    ];

    /** @var array<int, array{sales_order_item_id: ?int, product_id: ?int, unit_id: ?int, quantity: float, note: ?string}> */
    public array $items = [];

    public function mount(?Delivery $delivery = null, ?int $orderId = null): void
    {
        if ($delivery?->exists) {
            $this->deliveryId = $delivery->getKey();
            $this->form = [
                'sales_order_id' => $delivery->sales_order_id,
                'customer_id' => $delivery->customer_id,
                'customer_address_id' => $delivery->customer_address_id,
                'warehouse_id' => $delivery->warehouse_id,
                'delivery_date' => $delivery->delivery_date->toDateString(),
                'shipping_address' => $delivery->shipping_address,
                'driver_name' => $delivery->driver_name,
                'vehicle_number' => $delivery->vehicle_number,
                'note' => $delivery->note,
            ];

            $this->items = $delivery->items
                ->map(fn (DeliveryItem $item) => [
                    'sales_order_item_id' => $item->sales_order_item_id,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'note' => $item->note,
                ])->all();

            return;
        }

        $this->form['delivery_date'] = now()->toDateString();
        $this->form['warehouse_id'] = $this->defaultWarehouseId();

        if ($orderId !== null) {
            $this->loadOrder($orderId);

            return;
        }

        $this->addItemRow();
    }

    public function updatedForm(mixed $value, string $key): void
    {
        if ($key === 'sales_order_id' && $value) {
            $this->loadOrder((int) $value);
        }
    }

    public function addItemRow(): void
    {
        $this->items[] = [
            'sales_order_item_id' => null,
            'product_id' => null,
            'unit_id' => null,
            'quantity' => 1,
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

        if ($product !== null) {
            $this->items[$index]['unit_id'] = $this->items[$index]['unit_id'] ?: $product->base_unit_id;
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'form.sales_order_id' => ['nullable', 'integer', 'exists:sales_orders,id'],
            'form.customer_id' => ['required', 'integer', 'exists:customers,id'],
            'form.customer_address_id' => ['nullable', 'integer', 'exists:customer_addresses,id'],
            'form.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'form.delivery_date' => ['required', 'date'],
            'form.shipping_address' => ['nullable', 'string', 'max:255'],
            'form.driver_name' => ['nullable', 'string', 'max:100'],
            'form.vehicle_number' => ['nullable', 'string', 'max:30'],
            'form.note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sales_order_item_id' => ['nullable', 'integer', 'exists:sales_order_items,id'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ], [
            'items.required' => 'Minimal satu baris barang.',
        ]);

        try {
            $delivery = app(DeliveryService::class)->save(
                $this->deliveryId ? Delivery::findOrFail($this->deliveryId) : null,
                $validated['form'],
                $validated['items'],
            );
        } catch (RuntimeException $exception) {
            $this->addError('items', $exception->getMessage());

            return;
        }

        session()->flash('status', 'Surat jalan disimpan dan siap dipicking.');

        $this->redirectRoute('delivery.orders.detail', $delivery, navigate: true);
    }

    public function render()
    {
        return view('delivery.livewire.delivery-form', [
            'orders' => $this->openOrders(),
            'warehouses' => Warehouse::active()->orderBy('name')->get(),
            'products' => Product::active()->stocked()->orderBy('name')->limit(500)->get(),
            'units' => Unit::where('is_active', true)->orderBy('code')->pluck('code', 'id'),
            'outstanding' => $this->outstandingByOrderItem(),
        ]);
    }

    /**
     * Baris surat jalan diambil dari sisa order supaya pengiriman bertahap
     * tidak pernah melebihi yang dipesan.
     */
    private function loadOrder(int $orderId): void
    {
        $order = SalesOrder::with('items.product', 'customer')->find($orderId);

        if ($order === null || ! $order->isDeliverable()) {
            $this->addError('form.sales_order_id', 'Sales order ini belum disetujui atau sudah selesai.');

            return;
        }

        $this->form['sales_order_id'] = $order->getKey();
        $this->form['customer_id'] = $order->customer_id;
        $this->form['customer_address_id'] = $order->customer_address_id;
        $this->form['warehouse_id'] = $order->warehouse_id;
        $this->items = app(DeliveryService::class)->draftItemsFromOrder($order);

        if ($this->items === []) {
            $this->addError('form.sales_order_id', 'Seluruh barang pada order ini sudah dikirim.');
            $this->addItemRow();
        }
    }

    /**
     * @return array<int, float>
     */
    private function outstandingByOrderItem(): array
    {
        if (! $this->form['sales_order_id']) {
            return [];
        }

        $order = SalesOrder::with('items')->find($this->form['sales_order_id']);

        return $order === null
            ? []
            : $order->items
                ->mapWithKeys(fn ($item) => [$item->getKey() => $item->outstandingBaseQuantity()])
                ->all();
    }

    private function openOrders()
    {
        return SalesOrder::query()
            ->whereIn('status', [DocumentStatus::Approved, DocumentStatus::PartiallyProcessed])
            ->with('customer')
            ->orderByDesc('order_date')
            ->limit(200)
            ->get();
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
