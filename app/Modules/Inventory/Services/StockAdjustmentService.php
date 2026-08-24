<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Accounting\Events\BusinessDocumentPosted;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\InventoryTransactionType;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockAdjustmentItem;
use App\Modules\Product\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Penyesuaian stok memakai kuantitas bertanda: positif menambah, negatif
 * mengurangi. Setelah diposting dokumennya immutable (PLAN 29 dan 52).
 */
class StockAdjustmentService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly CostingService $costing,
        private readonly DocumentNumberService $numbers,
    ) {}

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, batch_id?: ?int, unit_cost?: ?float, note?: ?string}>  $items
     */
    public function save(?StockAdjustment $adjustment, array $attributes, array $items): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $attributes, $items): StockAdjustment {
            if ($adjustment !== null && ! $adjustment->status->isEditable()) {
                throw new RuntimeException('Penyesuaian yang sudah diposting tidak bisa diubah.');
            }

            $warehouse = Warehouse::findOrFail($attributes['warehouse_id']);
            $date = Carbon::parse($attributes['adjustment_date']);

            $adjustment ??= new StockAdjustment([
                'status' => DocumentStatus::Draft,
                'created_by' => Auth::id(),
            ]);

            $adjustment->fill([
                'warehouse_id' => $warehouse->getKey(),
                'branch_id' => $warehouse->branch_id,
                'adjustment_date' => $date->toDateString(),
                'reason' => $attributes['reason'],
                'note' => $attributes['note'] ?? null,
            ]);

            $adjustment->number ??= $this->numbers->next('stock_adjustment', $warehouse->branch_id, $date);
            $adjustment->save();

            $this->syncItems($adjustment, $items);

            return $adjustment->refresh();
        });
    }

    public function post(StockAdjustment $adjustment): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment): StockAdjustment {
            if ($adjustment->status !== DocumentStatus::Draft) {
                throw new RuntimeException('Hanya penyesuaian draft yang bisa diposting.');
            }

            $adjustment->load('items.product');

            if ($adjustment->items->isEmpty()) {
                throw new RuntimeException('Penyesuaian tanpa baris tidak bisa diposting.');
            }

            $totalValue = 0.0;

            foreach ($adjustment->items as $item) {
                $ledgers = $this->stock->record(new StockMovement(
                    product: $item->product,
                    warehouseId: (int) $adjustment->warehouse_id,
                    type: $this->movementType($adjustment->reason, $item->base_quantity),
                    quantity: abs($item->base_quantity),
                    unitId: (int) $item->product->base_unit_id,
                    unitCost: $item->unit_cost,
                    batchId: $item->batch_id,
                    documentType: 'stock_adjustment',
                    documentId: (int) $adjustment->getKey(),
                    documentNumber: $adjustment->number,
                    date: $adjustment->adjustment_date,
                    branchId: $adjustment->branch_id,
                    note: $item->note,
                ));

                $cost = collect($ledgers)->sum('total_cost');
                $signedCost = $item->base_quantity > 0 ? $cost : -$cost;

                $item->unit_cost = abs($item->base_quantity) > 0
                    ? round($cost / abs($item->base_quantity), 4)
                    : 0.0;
                $item->total_cost = round($signedCost, 4);
                $item->save();

                $totalValue += $signedCost;
            }

            $adjustment->status = DocumentStatus::Posted;
            $adjustment->total_value = round($totalValue, 4);
            $adjustment->posted_by = Auth::id();
            $adjustment->posted_at = now();
            $adjustment->save();

            event(new BusinessDocumentPosted('stock_adjustment', $adjustment->fresh()));

            return $adjustment;
        });
    }

    public function cancel(StockAdjustment $adjustment): StockAdjustment
    {
        if (! $adjustment->status->isEditable()) {
            throw new RuntimeException('Penyesuaian yang sudah diposting hanya bisa dikoreksi dengan penyesuaian baru.');
        }

        $adjustment->status = DocumentStatus::Cancelled;
        $adjustment->save();

        return $adjustment;
    }

    /**
     * Alasan menentukan jenis gerakan supaya laporan bisa memisahkan barang
     * rusak dan kedaluwarsa dari koreksi administratif.
     */
    private function movementType(string $reason, float $baseQuantity): InventoryTransactionType
    {
        if ($baseQuantity > 0) {
            return $reason === 'opening'
                ? InventoryTransactionType::OpeningBalance
                : InventoryTransactionType::AdjustmentIn;
        }

        return match ($reason) {
            'damaged' => InventoryTransactionType::Damaged,
            'expired' => InventoryTransactionType::Expired,
            default => InventoryTransactionType::AdjustmentOut,
        };
    }

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, batch_id?: ?int, unit_cost?: ?float, note?: ?string}>  $items
     */
    private function syncItems(StockAdjustment $adjustment, array $items): void
    {
        $adjustment->items()->delete();

        foreach ($items as $row) {
            $product = Product::findOrFail($row['product_id']);
            $unitId = (int) ($row['unit_id'] ?: $product->base_unit_id);
            $quantity = (float) $row['quantity'];
            $baseQuantity = $product->toBaseQuantity(abs($quantity), $unitId) * ($quantity < 0 ? -1 : 1);

            $unitCost = $row['unit_cost'] ?? $this->costing->unitCost(
                (int) $adjustment->company_id,
                (int) $adjustment->warehouse_id,
                (int) $product->getKey(),
                $row['batch_id'] ?? null,
            );

            StockAdjustmentItem::create([
                'company_id' => $adjustment->company_id,
                'stock_adjustment_id' => $adjustment->getKey(),
                'product_id' => $product->getKey(),
                'unit_id' => $unitId,
                'batch_id' => $row['batch_id'] ?? null,
                'quantity' => $quantity,
                'base_quantity' => $baseQuantity,
                'unit_cost' => $unitCost ?: $product->average_cost,
                'total_cost' => round($baseQuantity * ($unitCost ?: $product->average_cost), 4),
                'note' => $row['note'] ?? null,
            ]);
        }
    }
}
