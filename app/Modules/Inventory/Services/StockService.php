<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\InventoryTransactionType;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Product\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Satu-satunya pintu masuk perubahan stok (PLAN 24). Semua modul lain memanggil
 * service ini, tidak pernah menulis stock_ledgers atau stock_balances langsung,
 * sehingga saldo, layer biaya, dan kartu stok tidak mungkin saling bertentangan.
 */
class StockService
{
    public function __construct(
        private readonly CostingService $costing,
        private readonly ScopeManager $scope,
    ) {}

    /**
     * Catat pergerakan stok. Pengeluaran produk berbatch dipecah otomatis
     * mengikuti FEFO bila batch tidak ditentukan.
     *
     * @return array<int, StockLedger>
     */
    public function record(StockMovement $movement): array
    {
        if ($movement->type->direction() === 0) {
            throw new RuntimeException('Gerakan stok harus menambah atau mengurangi.');
        }

        return DB::transaction(function () use ($movement): array {
            if ($movement->type->isInbound()) {
                return [$this->applyInbound($movement)];
            }

            return $this->applyOutbound($movement);
        });
    }

    /**
     * @return array<int, StockLedger>
     */
    public function receive(StockMovement $movement): array
    {
        return $this->record($movement);
    }

    /**
     * @return array<int, StockLedger>
     */
    public function issue(StockMovement $movement): array
    {
        return $this->record($movement);
    }

    /**
     * Perpindahan antar gudang selalu dua gerakan agar kartu stok kedua
     * gudang tetap benar dan biaya terbawa dari gudang asal.
     *
     * @return array<int, StockLedger>
     */
    public function move(StockMovement $out, int $toWarehouseId): array
    {
        return DB::transaction(function () use ($out, $toWarehouseId): array {
            $outbound = $this->record(new StockMovement(
                product: $out->product,
                warehouseId: $out->warehouseId,
                type: InventoryTransactionType::TransferOut,
                quantity: $out->quantity,
                unitId: $out->unitId,
                batchId: $out->batchId,
                documentType: $out->documentType,
                documentId: $out->documentId,
                documentNumber: $out->documentNumber,
                date: $out->date,
                branchId: $out->branchId,
                note: $out->note,
            ));

            $inbound = [];

            foreach ($outbound as $ledger) {
                $batch = $ledger->batch_id ? Batch::withoutGlobalScopes()->find($ledger->batch_id) : null;

                $inbound = array_merge($inbound, $this->record(new StockMovement(
                    product: $out->product,
                    warehouseId: $toWarehouseId,
                    type: InventoryTransactionType::TransferIn,
                    quantity: abs($ledger->base_quantity),
                    unitId: (int) $out->product->base_unit_id,
                    unitCost: $ledger->unit_cost,
                    batchNumber: $batch?->batch_number,
                    expiryDate: $batch?->expiry_date,
                    supplierId: $batch?->supplier_id,
                    documentType: $out->documentType,
                    documentId: $out->documentId,
                    documentNumber: $out->documentNumber,
                    date: $out->date,
                    branchId: Warehouse::withoutGlobalScopes()->whereKey($toWarehouseId)->value('branch_id'),
                    note: $out->note,
                )));
            }

            return array_merge($outbound, $inbound);
        });
    }

    public function balance(int $productId, int $warehouseId): ?StockBalance
    {
        return StockBalance::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();
    }

    public function onHand(int $productId, int $warehouseId): float
    {
        return (float) (StockBalance::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity') ?? 0);
    }

    public function available(int $productId, int $warehouseId): float
    {
        $balance = $this->balance($productId, $warehouseId);

        return $balance?->availableQuantity() ?? 0.0;
    }

    /**
     * Reservasi menahan stok untuk order yang belum dikirim sehingga barang
     * yang sama tidak dijanjikan dua kali (PLAN 27).
     */
    public function reserve(int $productId, int $warehouseId, float $baseQuantity): void
    {
        DB::transaction(function () use ($productId, $warehouseId, $baseQuantity): void {
            $balance = $this->lockBalance($this->companyId(), $warehouseId, $productId);

            $balance->reserved_quantity = round($balance->reserved_quantity + $baseQuantity, 4);
            $balance->save();
        });
    }

    public function release(int $productId, int $warehouseId, float $baseQuantity): void
    {
        DB::transaction(function () use ($productId, $warehouseId, $baseQuantity): void {
            $balance = $this->lockBalance($this->companyId(), $warehouseId, $productId);

            $balance->reserved_quantity = max(0, round($balance->reserved_quantity - $baseQuantity, 4));
            $balance->save();
        });
    }

    /**
     * Alokasi FEFO: batch paling dekat kedaluwarsa keluar lebih dulu.
     *
     * @return array<int, array{batch_id: int, base_quantity: float}>
     */
    public function allocateFefo(Product $product, int $warehouseId, float $baseQuantity): array
    {
        $remaining = round($baseQuantity, 4);
        $allocation = [];

        $batches = Batch::withoutGlobalScopes()
            ->where('company_id', $product->company_id)
            ->where('product_id', $product->getKey())
            ->where('warehouse_id', $warehouseId)
            ->available()
            ->fefo()
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $taken = min($batch->quantity, $remaining);
            $allocation[] = ['batch_id' => (int) $batch->getKey(), 'base_quantity' => $taken];
            $remaining = round($remaining - $taken, 4);
        }

        if ($remaining > 0) {
            $this->assertNegativeAllowed($product, $warehouseId, $baseQuantity, $baseQuantity - $remaining);

            // Kekurangan dicatat tanpa batch supaya kartu stok tetap lengkap.
            $allocation[] = ['batch_id' => null, 'base_quantity' => $remaining];
        }

        return $allocation;
    }

    private function applyInbound(StockMovement $movement): StockLedger
    {
        $product = $movement->product;
        $companyId = (int) $product->company_id;
        $baseQuantity = $movement->baseQuantity();

        if ($baseQuantity <= 0) {
            throw new RuntimeException('Kuantitas penerimaan harus lebih besar dari nol.');
        }

        $unitCost = round((float) ($movement->unitCost ?? $product->average_cost), 4);
        $batch = $this->resolveInboundBatch($movement, $baseQuantity, $unitCost);

        $balance = $this->lockBalance($companyId, $movement->warehouseId, $product->getKey());

        $newQuantity = round($balance->quantity + $baseQuantity, 4);
        $newValue = round($balance->total_value + ($baseQuantity * $unitCost), 4);

        $balance->quantity = $newQuantity;
        $balance->total_value = $newValue;
        $balance->average_cost = $newQuantity > 0 ? round($newValue / $newQuantity, 4) : $unitCost;
        $balance->last_movement_at = $movement->occurredAt();
        $balance->save();

        $ledger = $this->writeLedger($movement, $baseQuantity, $unitCost, $balance, $batch?->getKey());

        $this->costing->addLayer(
            companyId: $companyId,
            warehouseId: $movement->warehouseId,
            productId: (int) $product->getKey(),
            baseQuantity: $baseQuantity,
            unitCost: $unitCost,
            batchId: $batch?->getKey(),
            ledgerId: (int) $ledger->getKey(),
            receivedAt: $movement->occurredAt(),
        );

        $this->refreshProductCost($product, $unitCost, $movement->type);

        return $ledger;
    }

    /**
     * @return array<int, StockLedger>
     */
    private function applyOutbound(StockMovement $movement): array
    {
        $product = $movement->product;
        $baseQuantity = $movement->baseQuantity();

        if ($baseQuantity <= 0) {
            throw new RuntimeException('Kuantitas pengeluaran harus lebih besar dari nol.');
        }

        if ($product->track_batch && $movement->batchId === null) {
            $ledgers = [];

            foreach ($this->allocateFefo($product, $movement->warehouseId, $baseQuantity) as $slice) {
                $ledgers[] = $this->writeOutbound(
                    $movement->withBatch($slice['batch_id'], $slice['base_quantity']),
                    $slice['base_quantity'],
                );
            }

            return $ledgers;
        }

        return [$this->writeOutbound($movement, $baseQuantity)];
    }

    private function writeOutbound(StockMovement $movement, float $baseQuantity): StockLedger
    {
        $product = $movement->product;
        $companyId = (int) $product->company_id;

        $balance = $this->lockBalance($companyId, $movement->warehouseId, $product->getKey());

        if (round($balance->quantity - $baseQuantity, 4) < 0) {
            $this->assertNegativeAllowed($product, $movement->warehouseId, $baseQuantity, $balance->quantity);
        }

        $totalCost = $this->costing->consume(
            companyId: $companyId,
            warehouseId: $movement->warehouseId,
            productId: (int) $product->getKey(),
            baseQuantity: $baseQuantity,
            batchId: $movement->batchId,
            fallbackCost: $balance->average_cost ?: $product->average_cost,
        );

        $unitCost = $baseQuantity > 0 ? round($totalCost / $baseQuantity, 4) : 0.0;

        $newQuantity = round($balance->quantity - $baseQuantity, 4);
        $newValue = round($balance->total_value - $totalCost, 4);

        $balance->quantity = $newQuantity;
        // Saldo nol harus bernilai nol; sisa pembulatan tidak boleh menempel.
        $balance->total_value = $newQuantity > 0 ? $newValue : 0.0;
        $balance->average_cost = $newQuantity > 0 ? round($balance->total_value / $newQuantity, 4) : $balance->average_cost;
        $balance->last_movement_at = $movement->occurredAt();
        $balance->save();

        if ($movement->batchId !== null) {
            $this->decrementBatch($movement->batchId, $baseQuantity);
        }

        return $this->writeLedger($movement, -$baseQuantity, $unitCost, $balance, $movement->batchId, $totalCost);
    }

    /**
     * Total biaya dikirim apa adanya bila tersedia: menghitung ulang dari
     * unit_cost yang sudah dibulatkan membuat nilai kartu stok melenceng dari
     * layer biaya yang benar-benar terpakai.
     */
    private function writeLedger(
        StockMovement $movement,
        float $signedBaseQuantity,
        float $unitCost,
        StockBalance $balance,
        ?int $batchId,
        ?float $totalCost = null,
    ): StockLedger {
        $product = $movement->product;
        $unitId = $movement->unitIdOrBase();
        $conversion = $product->conversionFor($unitId);

        return StockLedger::create([
            'company_id' => $product->company_id,
            'branch_id' => $movement->branchId
                ?? Warehouse::withoutGlobalScopes()->whereKey($movement->warehouseId)->value('branch_id'),
            'warehouse_id' => $movement->warehouseId,
            'product_id' => $product->getKey(),
            'batch_id' => $batchId,
            'unit_id' => $unitId,
            'transaction_type' => $movement->type,
            'transaction_date' => $movement->occurredAt()->toDateString(),
            'transaction_at' => $movement->occurredAt(),
            'document_type' => $movement->documentType,
            'document_id' => $movement->documentId,
            'document_number' => $movement->documentNumber,
            'quantity' => round($signedBaseQuantity / $conversion, 4),
            'base_quantity' => $signedBaseQuantity,
            'unit_cost' => $unitCost,
            'total_cost' => round($totalCost ?? abs($signedBaseQuantity) * $unitCost, 4),
            'balance_quantity' => $balance->quantity,
            'balance_value' => $balance->total_value,
            'note' => $movement->note,
            'created_by' => Auth::id(),
        ]);
    }

    private function resolveInboundBatch(StockMovement $movement, float $baseQuantity, float $unitCost): ?Batch
    {
        $product = $movement->product;

        if ($movement->batchId !== null) {
            $batch = Batch::withoutGlobalScopes()->findOrFail($movement->batchId);
            $batch->quantity = round($batch->quantity + $baseQuantity, 4);
            $batch->initial_quantity = round($batch->initial_quantity + $baseQuantity, 4);
            $batch->save();

            return $batch;
        }

        if (! $product->track_batch) {
            return null;
        }

        $number = $movement->batchNumber ?: $this->autoBatchNumber($movement);

        $batch = Batch::withoutGlobalScopes()->firstOrNew([
            'product_id' => $product->getKey(),
            'warehouse_id' => $movement->warehouseId,
            'batch_number' => $number,
        ]);

        $batch->company_id = $product->company_id;
        $batch->supplier_id ??= $movement->supplierId;
        $batch->received_date ??= $movement->occurredAt()->toDateString();
        $batch->expiry_date ??= $movement->expiryDate?->toDateString();
        $batch->initial_quantity = round(($batch->initial_quantity ?? 0) + $baseQuantity, 4);
        $batch->quantity = round(($batch->quantity ?? 0) + $baseQuantity, 4);
        $batch->unit_cost = $unitCost;
        $batch->is_active = true;
        $batch->save();

        return $batch;
    }

    private function decrementBatch(int $batchId, float $baseQuantity): void
    {
        $batch = Batch::withoutGlobalScopes()->lockForUpdate()->findOrFail($batchId);

        // Batch tidak boleh minus; kekurangan sudah dicatat tanpa batch.
        $batch->quantity = max(0, round($batch->quantity - $baseQuantity, 4));
        $batch->save();
    }

    private function lockBalance(int $companyId, int $warehouseId, int $productId): StockBalance
    {
        $balance = StockBalance::withoutGlobalScopes()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if ($balance !== null) {
            return $balance;
        }

        return StockBalance::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
        ]);
    }

    private function assertNegativeAllowed(Product $product, int $warehouseId, float $requested, float $available): void
    {
        if ($this->allowsNegative((int) $product->company_id)) {
            return;
        }

        throw InsufficientStockException::for(
            $product,
            $requested,
            max(0, $available),
            (string) (Warehouse::withoutGlobalScopes()->whereKey($warehouseId)->value('name') ?? 'gudang'),
        );
    }

    private function allowsNegative(int $companyId): bool
    {
        return (bool) (
            Company::withoutGlobalScopes()->whereKey($companyId)->value('allow_negative_stock')
            ?? config('oasse.inventory.allow_negative_stock')
        );
    }

    /**
     * Harga pokok rata-rata di master produk dipakai untuk margin guard dan
     * laporan lintas gudang, jadi ikut diperbarui setiap penerimaan.
     */
    private function refreshProductCost(Product $product, float $unitCost, InventoryTransactionType $type): void
    {
        $aggregate = StockBalance::withoutGlobalScopes()
            ->where('product_id', $product->getKey())
            ->selectRaw('COALESCE(SUM(quantity), 0) as qty, COALESCE(SUM(total_value), 0) as value')
            ->first();

        $quantity = (float) $aggregate->qty;
        $value = (float) $aggregate->value;

        $product->average_cost = $quantity > 0 ? round($value / $quantity, 4) : $unitCost;

        if ($type === InventoryTransactionType::Purchase) {
            $product->last_purchase_cost = $unitCost;
        }

        $product->saveQuietly();
    }

    private function companyId(): int
    {
        $companyId = $this->scope->companyId();

        if ($companyId === null) {
            throw new RuntimeException('Company belum ditentukan untuk operasi stok.');
        }

        return $companyId;
    }

    /**
     * Batch otomatis memakai nomor dokumen bila ada, agar mudah dilacak balik
     * ke penerimaan yang membuatnya.
     */
    private function autoBatchNumber(StockMovement $movement): string
    {
        $suffix = $movement->documentNumber
            ? str_replace('/', '-', $movement->documentNumber)
            : Carbon::now()->format('YmdHis');

        return 'B-'.$suffix;
    }
}
