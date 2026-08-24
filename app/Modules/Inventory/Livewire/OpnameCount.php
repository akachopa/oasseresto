<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Livewire;

use App\Modules\Inventory\Models\StockOpname;
use App\Modules\Inventory\Models\StockOpnameItem;
use App\Modules\Inventory\Services\StockOpnameService;
use Livewire\Component;
use RuntimeException;

/**
 * Lembar hitung dipakai di gudang, sering dari ponsel, jadi tampilannya
 * berbasis kartu dan input hitung disimpan bertahap (PLAN 30).
 */
class OpnameCount extends Component
{
    public int $opnameId;

    public string $search = '';

    public string $filter = 'all';

    /** @var array<int, ?string> */
    public array $counts = [];

    public function mount(StockOpname $opname): void
    {
        $this->opnameId = $opname->getKey();

        $this->counts = $opname->items
            ->mapWithKeys(fn (StockOpnameItem $item) => [
                $item->getKey() => $item->counted_base_quantity === null
                    ? null
                    : (string) $item->counted_base_quantity,
            ])->all();
    }

    public function saveCounts(): void
    {
        $opname = $this->opname();

        $payload = [];

        foreach ($this->counts as $itemId => $value) {
            $payload[] = [
                'item_id' => (int) $itemId,
                'counted_base_quantity' => ($value === null || $value === '') ? null : (float) $value,
            ];
        }

        try {
            app(StockOpnameService::class)->saveCounts($opname, $payload);
        } catch (RuntimeException $exception) {
            $this->addError('counts', $exception->getMessage());

            return;
        }

        session()->flash('status', 'Hasil hitung tersimpan.');
    }

    public function post(): void
    {
        $this->saveCounts();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        try {
            app(StockOpnameService::class)->post($this->opname());
        } catch (RuntimeException $exception) {
            $this->addError('counts', $exception->getMessage());

            return;
        }

        session()->flash('status', 'Opname diposting dan selisihnya masuk kartu stok.');
        $this->redirectRoute('inventory.opnames.detail', $this->opnameId, navigate: true);
    }

    /**
     * Menyamakan hitungan dengan saldo sistem untuk baris yang jelas cocok,
     * supaya petugas hanya fokus pada barang yang selisih.
     */
    public function acceptSystem(int $itemId): void
    {
        $item = $this->opname()->items()->findOrFail($itemId);
        $this->counts[$itemId] = (string) $item->system_base_quantity;
    }

    public function render()
    {
        $opname = $this->opname();

        $items = $opname->items()
            ->with('product.baseUnit', 'batch')
            ->when($this->search !== '', function ($query): void {
                $query->whereHas('product', fn ($product) => $product
                    ->where('name', 'ilike', "%{$this->search}%")
                    ->orWhere('sku', 'ilike', "%{$this->search}%"));
            })
            ->when($this->filter === 'uncounted', fn ($query) => $query->where('is_counted', false))
            ->when($this->filter === 'difference', fn ($query) => $query
                ->where('is_counted', true)
                ->where('difference_base_quantity', '!=', 0))
            ->orderBy('id')
            ->get();

        return view('inventory.livewire.opname-count', [
            'opname' => $opname,
            'items' => $items,
            'countedCount' => $opname->items()->where('is_counted', true)->count(),
            'totalCount' => $opname->items()->count(),
        ]);
    }

    private function opname(): StockOpname
    {
        return StockOpname::findOrFail($this->opnameId);
    }
}
