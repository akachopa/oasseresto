<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Company\Models\Company;
use App\Modules\Core\Enums\CostingMethod;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCostLayer;
use Illuminate\Support\Carbon;

/**
 * Menentukan harga pokok setiap pengeluaran stok (PLAN 25).
 *
 * FIFO memakai layer biaya per penerimaan; weighted average memakai
 * average_cost pada stock_balances. Kedua metode tetap menulis layer supaya
 * company bisa berpindah metode tanpa kehilangan riwayat biaya.
 */
class CostingService
{
    /** @var array<int, CostingMethod> */
    private array $methodCache = [];

    public function methodFor(int $companyId): CostingMethod
    {
        return $this->methodCache[$companyId] ??= Company::withoutGlobalScopes()
            ->whereKey($companyId)
            ->value('costing_method')
            ?? CostingMethod::from((string) config('oasse.inventory.costing_method', 'weighted_average'));
    }

    public function addLayer(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $baseQuantity,
        float $unitCost,
        ?int $batchId = null,
        ?int $ledgerId = null,
        ?Carbon $receivedAt = null,
    ): StockCostLayer {
        return StockCostLayer::create([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'batch_id' => $batchId,
            'stock_ledger_id' => $ledgerId,
            'received_at' => $receivedAt ?? Carbon::now(),
            'quantity' => $baseQuantity,
            'remaining_quantity' => $baseQuantity,
            'unit_cost' => $unitCost,
        ]);
    }

    /**
     * Konsumsi biaya untuk pengeluaran stok. Mengembalikan total biaya dalam
     * satuan dasar; layer FIFO yang terpakai langsung dikurangi.
     */
    public function consume(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $baseQuantity,
        ?int $batchId = null,
        ?float $fallbackCost = null,
    ): float {
        if ($baseQuantity <= 0) {
            return 0.0;
        }

        $method = $this->methodFor($companyId);

        if ($method === CostingMethod::WeightedAverage) {
            $this->drainLayers($companyId, $warehouseId, $productId, $baseQuantity, $batchId);

            $average = $this->averageCost($companyId, $warehouseId, $productId)
                ?? $fallbackCost
                ?? 0.0;

            return round($average * $baseQuantity, 4);
        }

        return $this->consumeFifo($companyId, $warehouseId, $productId, $baseQuantity, $batchId, $fallbackCost);
    }

    /**
     * Biaya per satuan dasar tanpa mengubah layer. Dipakai untuk pratinjau
     * margin dan estimasi nilai opname.
     */
    public function unitCost(int $companyId, int $warehouseId, int $productId, ?int $batchId = null): float
    {
        if ($this->methodFor($companyId) === CostingMethod::WeightedAverage) {
            return $this->averageCost($companyId, $warehouseId, $productId) ?? 0.0;
        }

        $layer = StockCostLayer::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->open()
            ->oldestFirst()
            ->first();

        return (float) ($layer?->unit_cost ?? $this->averageCost($companyId, $warehouseId, $productId) ?? 0.0);
    }

    private function consumeFifo(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $baseQuantity,
        ?int $batchId,
        ?float $fallbackCost,
    ): float {
        $remaining = round($baseQuantity, 4);
        $total = 0.0;

        $layers = StockCostLayer::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->open()
            ->oldestFirst()
            ->lockForUpdate()
            ->get();

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $taken = min($layer->remaining_quantity, $remaining);
            $total += $taken * $layer->unit_cost;
            $remaining = round($remaining - $taken, 4);

            $layer->remaining_quantity = round($layer->remaining_quantity - $taken, 4);
            $layer->save();
        }

        /*
         * Sisa yang tidak tertutup layer hanya terjadi saat stok minus
         * diizinkan. Biayanya memakai layer terakhir atau harga pokok
         * rata-rata supaya jurnal tetap seimbang.
         */
        if ($remaining > 0) {
            $cost = $fallbackCost
                ?? $this->averageCost($companyId, $warehouseId, $productId)
                ?? (float) ($layers->last()?->unit_cost ?? 0);

            $total += $remaining * $cost;
        }

        return round($total, 4);
    }

    /**
     * Weighted average tetap mengosongkan layer agar valuasi FIFO dan
     * rata-rata tidak berbeda kuantitasnya bila metode diganti.
     */
    private function drainLayers(
        int $companyId,
        int $warehouseId,
        int $productId,
        float $baseQuantity,
        ?int $batchId,
    ): void {
        $remaining = round($baseQuantity, 4);

        $layers = StockCostLayer::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->open()
            ->oldestFirst()
            ->lockForUpdate()
            ->get();

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $taken = min($layer->remaining_quantity, $remaining);
            $remaining = round($remaining - $taken, 4);

            $layer->remaining_quantity = round($layer->remaining_quantity - $taken, 4);
            $layer->save();
        }
    }

    private function averageCost(int $companyId, int $warehouseId, int $productId): ?float
    {
        $balance = StockBalance::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->first();

        if ($balance === null || $balance->average_cost <= 0) {
            return null;
        }

        return $balance->average_cost;
    }
}
