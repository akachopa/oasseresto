<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Livewire;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\StockTransferItem;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Inventory\Services\StockTransferService;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use Livewire\Component;
use RuntimeException;

class TransferForm extends Component
{
    public ?int $transferId = null;

    public array $form = [
        'from_warehouse_id' => null,
        'to_warehouse_id' => null,
        'transfer_date' => '',
        'note' => '',
    ];

    /** @var array<int, array{product_id: ?int, unit_id: ?int, batch_id: ?int, quantity: float, note: ?string}> */
    public array $items = [];

    public function mount(?StockTransfer $transfer = null): void
    {
        if ($transfer?->exists) {
            $this->transferId = $transfer->getKey();
            $this->form = [
                'from_warehouse_id' => $transfer->from_warehouse_id,
                'to_warehouse_id' => $transfer->to_warehouse_id,
                'transfer_date' => $transfer->transfer_date->toDateString(),
                'note' => $transfer->note,
            ];

            $this->items = $transfer->items
                ->map(fn (StockTransferItem $item) => [
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'batch_id' => $item->batch_id,
                    'quantity' => $item->quantity,
                    'note' => $item->note,
                ])->all();

            return;
        }

        $this->form['transfer_date'] = now()->toDateString();
        $this->form['from_warehouse_id'] = $this->defaultWarehouseId();
        $this->addItemRow();
    }

    public function addItemRow(): void
    {
        $this->items[] = [
            'product_id' => null,
            'unit_id' => null,
            'batch_id' => null,
            'quantity' => 1,
            'note' => null,
        ];
    }

    public function removeItemRow(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    /**
     * Satuan mengikuti satuan dasar produk agar operator tidak perlu memilih
     * ulang untuk kasus paling umum.
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

        $this->items[$index]['unit_id'] ??= $product->base_unit_id;
        $this->items[$index]['unit_id'] = $this->items[$index]['unit_id'] ?: $product->base_unit_id;
        $this->items[$index]['batch_id'] = null;
    }

    public function save(bool $andShip = false): void
    {
        $validated = $this->validate([
            'form.from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'form.to_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:form.from_warehouse_id'],
            'form.transfer_date' => ['required', 'date'],
            'form.note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ], [
            'form.to_warehouse_id.different' => 'Gudang tujuan harus berbeda dari gudang asal.',
            'items.required' => 'Minimal satu baris barang.',
        ]);

        try {
            $transfer = app(StockTransferService::class)->save(
                $this->transferId ? StockTransfer::findOrFail($this->transferId) : null,
                $validated['form'],
                $validated['items'],
            );

            if ($andShip) {
                app(StockTransferService::class)->ship($transfer);
            }
        } catch (RuntimeException $exception) {
            $this->addError('items', $exception->getMessage());

            return;
        }

        session()->flash('status', $andShip
            ? 'Transfer dikirim dan stok gudang asal sudah berkurang.'
            : 'Transfer disimpan sebagai draft.');

        $this->redirectRoute('inventory.transfers.detail', $transfer, navigate: true);
    }

    public function render()
    {
        return view('inventory.livewire.transfer-form', [
            'warehouses' => Warehouse::active()->orderBy('name')->get(),
            'products' => Product::active()->stocked()->orderBy('name')->limit(500)->get(),
            'units' => Unit::where('is_active', true)->orderBy('code')->pluck('code', 'id'),
            'batchOptions' => $this->batchOptions(),
            'availability' => $this->availability(),
        ]);
    }

    /**
     * @return array<int, array<int, Batch>>
     */
    private function batchOptions(): array
    {
        $warehouseId = $this->form['from_warehouse_id'];

        if (! $warehouseId) {
            return [];
        }

        $productIds = array_filter(array_column($this->items, 'product_id'));

        if ($productIds === []) {
            return [];
        }

        return Batch::whereIn('product_id', $productIds)
            ->where('warehouse_id', $warehouseId)
            ->available()
            ->fefo()
            ->get()
            ->groupBy('product_id')
            ->map(fn ($batches) => $batches->all())
            ->all();
    }

    /**
     * @return array<int, float>
     */
    private function availability(): array
    {
        $warehouseId = $this->form['from_warehouse_id'];

        if (! $warehouseId) {
            return [];
        }

        $stock = app(StockService::class);

        return collect($this->items)
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->mapWithKeys(fn ($productId) => [
                (int) $productId => $stock->available((int) $productId, (int) $warehouseId),
            ])
            ->all();
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
