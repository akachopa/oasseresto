<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Livewire;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use App\Modules\Purchase\Models\PurchaseRequest;
use App\Modules\Purchase\Models\PurchaseRequestItem;
use App\Modules\Purchase\Services\PurchaseRequestService;
use App\Modules\Supplier\Models\Supplier;
use Livewire\Component;
use RuntimeException;

class RequestForm extends Component
{
    public ?int $requestId = null;

    public array $form = [
        'warehouse_id' => null,
        'request_date' => '',
        'needed_date' => null,
        'priority' => 'normal',
        'note' => '',
    ];

    /** @var array<int, array{product_id: ?int, unit_id: ?int, quantity: float, estimated_price: ?float, suggested_supplier_id: ?int, note: ?string}> */
    public array $items = [];

    public function mount(?PurchaseRequest $request = null): void
    {
        if ($request?->exists) {
            $this->requestId = $request->getKey();
            $this->form = [
                'warehouse_id' => $request->warehouse_id,
                'request_date' => $request->request_date->toDateString(),
                'needed_date' => $request->needed_date?->toDateString(),
                'priority' => $request->priority,
                'note' => $request->note,
            ];

            $this->items = $request->items
                ->map(fn (PurchaseRequestItem $item) => [
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'estimated_price' => $item->estimated_price,
                    'suggested_supplier_id' => $item->suggested_supplier_id,
                    'note' => $item->note,
                ])->all();

            return;
        }

        $this->form['request_date'] = now()->toDateString();
        $this->form['needed_date'] = now()->addWeek()->toDateString();
        $this->form['warehouse_id'] = $this->defaultWarehouseId();
        $this->addItemRow();
    }

    public function addItemRow(): void
    {
        $this->items[] = [
            'product_id' => null,
            'unit_id' => null,
            'quantity' => 1,
            'estimated_price' => null,
            'suggested_supplier_id' => null,
            'note' => null,
        ];
    }

    public function removeItemRow(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    /**
     * Estimasi harga diisi dari harga beli terakhir supaya nilai PR sudah
     * mendekati kenyataan sebelum negosiasi dengan supplier.
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

        $this->items[$index]['unit_id'] = $this->items[$index]['unit_id'] ?: $product->base_unit_id;
        $this->items[$index]['estimated_price'] = $product->last_purchase_cost ?: $product->average_cost;
    }

    public function save(bool $andSubmit = false): void
    {
        $validated = $this->validate([
            'form.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'form.request_date' => ['required', 'date'],
            'form.needed_date' => ['nullable', 'date', 'after_or_equal:form.request_date'],
            'form.priority' => ['required', 'in:'.implode(',', array_keys(PurchaseRequest::priorities()))],
            'form.note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.estimated_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.suggested_supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ], [
            'form.needed_date.after_or_equal' => 'Tanggal kebutuhan tidak boleh sebelum tanggal permintaan.',
            'items.required' => 'Minimal satu baris barang.',
        ]);

        try {
            $requests = app(PurchaseRequestService::class);

            $purchaseRequest = $requests->save(
                $this->requestId ? PurchaseRequest::findOrFail($this->requestId) : null,
                $validated['form'],
                $validated['items'],
            );

            if ($andSubmit) {
                $requests->submit($purchaseRequest);
            }
        } catch (RuntimeException $exception) {
            $this->addError('items', $exception->getMessage());

            return;
        }

        session()->flash('status', $andSubmit
            ? 'Purchase request diajukan untuk approval.'
            : 'Purchase request disimpan sebagai draft.');

        $this->redirectRoute('purchase.requests.detail', $purchaseRequest, navigate: true);
    }

    public function render()
    {
        return view('purchase.livewire.request-form', [
            'warehouses' => Warehouse::active()->orderBy('name')->get(),
            'products' => Product::active()->orderBy('name')->limit(500)->get(),
            'units' => Unit::where('is_active', true)->orderBy('code')->pluck('code', 'id'),
            'suppliers' => Supplier::active()->orderBy('name')->pluck('name', 'id'),
            'priorities' => PurchaseRequest::priorities(),
            'estimatedTotal' => $this->estimatedTotal(),
        ]);
    }

    private function estimatedTotal(): float
    {
        return round(collect($this->items)->sum(
            fn (array $row) => (float) ($row['quantity'] ?? 0) * (float) ($row['estimated_price'] ?? 0),
        ), 4);
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
