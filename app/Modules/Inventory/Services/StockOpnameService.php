<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Accounting\Events\BusinessDocumentPosted;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\InventoryTransactionType;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockOpname;
use App\Modules\Inventory\Models\StockOpnameItem;
use App\Modules\Product\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stock opname membandingkan hitungan fisik dengan saldo sistem lalu menulis
 * selisihnya sebagai gerakan stok (PLAN 30). Saldo sistem diambil ulang saat
 * posting supaya transaksi yang terjadi selama penghitungan tidak hilang.
 */
class StockOpnameService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly CostingService $costing,
        private readonly DocumentNumberService $numbers,
    ) {}

    public function create(array $attributes): StockOpname
    {
        return DB::transaction(function () use ($attributes): StockOpname {
            $warehouse = Warehouse::findOrFail($attributes['warehouse_id']);
            $date = Carbon::parse($attributes['opname_date']);

            $opname = StockOpname::create([
                'warehouse_id' => $warehouse->getKey(),
                'branch_id' => $warehouse->branch_id,
                'number' => $this->numbers->next('stock_opname', $warehouse->branch_id, $date),
                'opname_date' => $date->toDateString(),
                'status' => DocumentStatus::Draft,
                'scope_type' => $attributes['scope_type'] ?? 'full',
                'product_category_id' => $attributes['product_category_id'] ?? null,
                'note' => $attributes['note'] ?? null,
                'created_by' => Auth::id(),
                'counted_by' => Auth::id(),
            ]);

            $this->generateItems($opname);

            return $opname->refresh();
        });
    }

    /**
     * Isi daftar hitung dari saldo yang ada. Produk berbatch dipecah per batch
     * agar petugas menghitung lot yang benar.
     */
    public function generateItems(StockOpname $opname): void
    {
        $opname->items()->delete();

        $balances = StockBalance::query()
            ->with('product')
            ->where('warehouse_id', $opname->warehouse_id)
            ->when(
                $opname->product_category_id,
                fn ($query) => $query->whereHas(
                    'product',
                    fn ($product) => $product->where('product_category_id', $opname->product_category_id),
                ),
            )
            ->get()
            ->filter(fn (StockBalance $balance) => $balance->product !== null && $balance->product->is_stocked);

        foreach ($balances as $balance) {
            $product = $balance->product;

            if ($product->track_batch) {
                $batches = Batch::where('product_id', $product->getKey())
                    ->where('warehouse_id', $opname->warehouse_id)
                    ->available()
                    ->fefo()
                    ->get();

                foreach ($batches as $batch) {
                    $this->makeItem($opname, $product, $batch->quantity, $batch->getKey());
                }

                continue;
            }

            $this->makeItem($opname, $product, $balance->quantity, null);
        }
    }

    /**
     * @param  array<int, array{item_id: int, counted_base_quantity: ?float, note?: ?string}>  $counts
     */
    public function saveCounts(StockOpname $opname, array $counts): StockOpname
    {
        if (! $opname->isCountable()) {
            throw new RuntimeException('Opname ini sudah tidak bisa dihitung ulang.');
        }

        return DB::transaction(function () use ($opname, $counts): StockOpname {
            foreach ($counts as $row) {
                $item = $opname->items()->findOrFail($row['item_id']);
                $counted = $row['counted_base_quantity'];

                $item->counted_base_quantity = $counted === null ? null : round((float) $counted, 4);
                $item->is_counted = $counted !== null;
                $item->difference_base_quantity = $counted === null
                    ? 0.0
                    : round((float) $counted - $item->system_base_quantity, 4);
                $item->difference_value = round($item->difference_base_quantity * $item->unit_cost, 4);
                $item->note = $row['note'] ?? $item->note;
                $item->save();
            }

            $this->refreshTotals($opname);

            return $opname->refresh();
        });
    }

    public function submit(StockOpname $opname): StockOpname
    {
        if ($opname->status !== DocumentStatus::Draft) {
            throw new RuntimeException('Opname sudah diajukan.');
        }

        $opname->status = DocumentStatus::Submitted;
        $opname->counted_by = Auth::id();
        $opname->save();

        return $opname;
    }

    public function post(StockOpname $opname): StockOpname
    {
        return DB::transaction(function () use ($opname): StockOpname {
            if (! in_array($opname->status, [DocumentStatus::Draft, DocumentStatus::Submitted], true)) {
                throw new RuntimeException('Opname ini sudah diposting.');
            }

            $opname->load('items.product');

            $uncounted = $opname->items->where('is_counted', false)->count();

            if ($uncounted > 0) {
                throw new RuntimeException("Masih ada {$uncounted} baris yang belum dihitung.");
            }

            $differenceValue = 0.0;
            $differenceCount = 0;

            foreach ($opname->items as $item) {
                // Saldo dibaca ulang: transaksi selama penghitungan ikut dihitung.
                $current = $this->currentQuantity($opname, $item);
                $difference = round((float) $item->counted_base_quantity - $current, 4);

                $item->system_base_quantity = $current;
                $item->difference_base_quantity = $difference;
                $item->difference_value = round($difference * $item->unit_cost, 4);
                $item->save();

                if ($difference === 0.0) {
                    continue;
                }

                $this->stock->record(new StockMovement(
                    product: $item->product,
                    warehouseId: (int) $opname->warehouse_id,
                    type: $difference > 0
                        ? InventoryTransactionType::AdjustmentIn
                        : InventoryTransactionType::AdjustmentOut,
                    quantity: abs($difference),
                    unitId: (int) $item->product->base_unit_id,
                    unitCost: $item->unit_cost,
                    batchId: $item->batch_id,
                    documentType: 'stock_opname',
                    documentId: (int) $opname->getKey(),
                    documentNumber: $opname->number,
                    date: $opname->opname_date,
                    branchId: $opname->branch_id,
                    note: $item->note,
                ));

                $differenceValue += $item->difference_value;
                $differenceCount++;
            }

            $opname->status = DocumentStatus::Posted;
            $opname->difference_value = round($differenceValue, 4);
            $opname->difference_count = $differenceCount;
            $opname->posted_by = Auth::id();
            $opname->posted_at = now();
            $opname->save();

            event(new BusinessDocumentPosted('stock_opname', $opname->fresh()));

            return $opname;
        });
    }

    public function cancel(StockOpname $opname): StockOpname
    {
        if ($opname->status === DocumentStatus::Posted) {
            throw new RuntimeException('Opname yang sudah diposting tidak bisa dibatalkan.');
        }

        $opname->status = DocumentStatus::Cancelled;
        $opname->save();

        return $opname;
    }

    private function makeItem(StockOpname $opname, Product $product, float $systemQuantity, ?int $batchId): void
    {
        StockOpnameItem::create([
            'company_id' => $opname->company_id,
            'stock_opname_id' => $opname->getKey(),
            'product_id' => $product->getKey(),
            'batch_id' => $batchId,
            'system_base_quantity' => $systemQuantity,
            'unit_cost' => $this->costing->unitCost(
                (int) $opname->company_id,
                (int) $opname->warehouse_id,
                (int) $product->getKey(),
                $batchId,
            ) ?: $product->average_cost,
        ]);
    }

    private function currentQuantity(StockOpname $opname, StockOpnameItem $item): float
    {
        if ($item->batch_id !== null) {
            return (float) (Batch::withoutGlobalScopes()->whereKey($item->batch_id)->value('quantity') ?? 0);
        }

        return $this->stock->onHand((int) $item->product_id, (int) $opname->warehouse_id);
    }

    private function refreshTotals(StockOpname $opname): void
    {
        $items = $opname->items()->get();

        $opname->difference_value = round((float) $items->sum('difference_value'), 4);
        $opname->difference_count = $items->filter(
            fn (StockOpnameItem $item) => $item->is_counted && $item->difference_base_quantity !== 0.0,
        )->count();
        $opname->save();
    }
}
