<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Livewire;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockAdjustmentItem;
use App\Modules\Inventory\Services\CostingService;
use App\Modules\Inventory\Services\StockAdjustmentService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use Livewire\Component;
use RuntimeException;

class AdjustmentForm extends Component
{
    public ?int $adjustmentId = null;

    public array $form = [
        'warehouse_id' => null,
        'adjustment_date' => '',
        'reason' => 'correction',
        'note' => '',
    ];

    /** @var array<int, array{product_id: ?int, unit_id: ?int, batch_id: ?int, quantity: float, unit_cost: ?float, note: ?string}> */
    public array $items = [];

    public function mount(?StockAdjustment $adjustment = null): void
    {
        if ($adjustment?->exists) {
            $this->adjustmentId = $adjustment->getKey();
            $this->form = [
                'warehouse_id' => $adjustment->warehouse_id,
                'adjustment_date' => $adjustment->adjustment_date->toDateString(),
                'reason' => $adjustment->reason,
                'note' => $adjustment->note,
            ];

            $this->items = $adjustment->items
                ->map(fn (StockAdjustmentItem $item) => [
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'batch_id' => $item->batch_id,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'note' => $item->note,
                ])->all();

            return;
        }

        $this->form['adjustment_date'] = now()->toDateString();
        $this->form['warehouse_id'] = $this->defaultWarehouseId();
        $this->addItemRow();
    }

    public function addItemRow(): void
    {
        $this->items[] = [
            'product_id' => null,
            'unit_id' => null,
            'batch_id' => null,
            'quantity' => 0,
            'unit_cost' => null,
            'note' => null,
        ];
    }

    public function removeItemRow(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    /**
     * Harga pokok diisi otomatis dari layer biaya gudang terkait agar nilai
     * penyesuaian konsisten dengan valuasi persediaan.
     */
    public function updatedItems(mixed $value, string $key): void
    {
        if (! str_ends_with($key, '.product_id') || ! $value) {
            return;
        }

        $index = (int) explode('.', $key)[0];
        $product = Product::find($value);

        if ($product === null || ! $this->form['warehouse_id']) {
            return;
        }

        $this->items[$index]['unit_id'] = $this->items[$index]['unit_id'] ?: $product->base_unit_id;
        $this->items[$index]['batch_id'] = null;
        $this->items[$index]['unit_cost'] = app(CostingService::class)->unitCost(
            (int) $product->company_id,
            (int) $this->form['warehouse_id'],
            (int) $product->getKey(),
        ) ?: $product->average_cost;
    }

    public function save(bool $andPost = false): void
    {
        $validated = $this->validate([
            'form.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'form.adjustment_date' => ['required', 'date'],
            'form.reason' => ['required', 'in:'.implode(',', array_keys(StockAdjustment::reasons()))],
            'form.note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'items.*.quantity' => ['required', 'numeric', 'not_in:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ], [
            'items.*.quantity.not_in' => 'Kuantitas tidak boleh nol; pakai minus untuk mengurangi.',
        ]);

        try {
            $adjustment = app(StockAdjustmentService::class)->save(
                $this->adjustmentId ? StockAdjustment::findOrFail($this->adjustmentId) : null,
                $validated['form'],
                $validated['items'],
            );

            if ($andPost) {
                app(StockAdjustmentService::class)->post($adjustment);
            }
        } catch (RuntimeException $exception) {
            $this->addError('items', $exception->getMessage());

            return;
        }

        session()->flash('status', $andPost
            ? 'Penyesuaian diposting dan kartu stok sudah diperbarui.'
            : 'Penyesuaian disimpan sebagai draft.');

        $this->redirectRoute('inventory.adjustments.detail', $adjustment, navigate: true);
    }

    public function render()
    {
        return view('inventory.livewire.adjustment-form', [
            'warehouses' => Warehouse::active()->orderBy('name')->get(),
            'products' => Product::active()->stocked()->orderBy('name')->limit(500)->get(),
            'units' => Unit::where('is_active', true)->orderBy('code')->pluck('code', 'id'),
            'reasons' => StockAdjustment::reasons(),
            'batchOptions' => $this->batchOptions(),
            'availability' => $this->availability(),
        ]);
    }

    /**
     * @return array<int, array<int, Batch>>
     */
    private function batchOptions(): array
    {
        $productIds = array_filter(array_column($this->items, 'product_id'));

        if ($productIds === [] || ! $this->form['warehouse_id']) {
            return [];
        }

        return Batch::whereIn('product_id', $productIds)
            ->where('warehouse_id', $this->form['warehouse_id'])
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
        if (! $this->form['warehouse_id']) {
            return [];
        }

        $stock = app(StockService::class);

        return collect($this->items)
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->mapWithKeys(fn ($productId) => [
                (int) $productId => $stock->onHand((int) $productId, (int) $this->form['warehouse_id']),
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
