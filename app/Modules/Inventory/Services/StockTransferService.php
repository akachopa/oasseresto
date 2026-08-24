<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\InventoryTransactionType;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\StockTransferItem;
use App\Modules\Product\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Transfer punya dua tahap: kirim dan terima (PLAN 28). Di antara keduanya
 * barang berstatus in-transit sehingga tidak terhitung di kedua gudang.
 */
class StockTransferService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly DocumentNumberService $numbers,
    ) {}

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, batch_id?: ?int, note?: ?string}>  $items
     */
    public function save(?StockTransfer $transfer, array $attributes, array $items): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $attributes, $items): StockTransfer {
            if ($transfer !== null && ! $transfer->status->isEditable()) {
                throw new RuntimeException('Transfer yang sudah dikirim tidak bisa diubah.');
            }

            $fromWarehouse = Warehouse::findOrFail($attributes['from_warehouse_id']);
            $toWarehouse = Warehouse::findOrFail($attributes['to_warehouse_id']);
            $date = Carbon::parse($attributes['transfer_date']);

            $transfer ??= new StockTransfer([
                'status' => DocumentStatus::Draft,
                'created_by' => Auth::id(),
            ]);

            $transfer->fill([
                'from_warehouse_id' => $fromWarehouse->getKey(),
                'to_warehouse_id' => $toWarehouse->getKey(),
                'from_branch_id' => $fromWarehouse->branch_id,
                'to_branch_id' => $toWarehouse->branch_id,
                'transfer_date' => $date->toDateString(),
                'note' => $attributes['note'] ?? null,
            ]);

            $transfer->number ??= $this->numbers->next('stock_transfer', $fromWarehouse->branch_id, $date);
            $transfer->save();

            $this->syncItems($transfer, $items);

            return $transfer->refresh();
        });
    }

    /**
     * Kirim barang: stok keluar dari gudang asal dan dokumen jadi in-transit.
     */
    public function ship(StockTransfer $transfer): StockTransfer
    {
        return DB::transaction(function () use ($transfer): StockTransfer {
            if ($transfer->status !== DocumentStatus::Draft) {
                throw new RuntimeException('Hanya transfer draft yang bisa dikirim.');
            }

            $transfer->load('items.product');
            $totalValue = 0.0;

            foreach ($transfer->items as $item) {
                $ledgers = $this->stock->issue(new StockMovement(
                    product: $item->product,
                    warehouseId: (int) $transfer->from_warehouse_id,
                    type: InventoryTransactionType::TransferOut,
                    quantity: $item->base_quantity,
                    unitId: (int) $item->product->base_unit_id,
                    batchId: $item->batch_id,
                    documentType: 'stock_transfer',
                    documentId: (int) $transfer->getKey(),
                    documentNumber: $transfer->number,
                    date: $transfer->transfer_date,
                    branchId: $transfer->from_branch_id,
                    note: $item->note,
                ));

                $cost = collect($ledgers)->sum('total_cost');
                $totalValue += $cost;

                $item->unit_cost = $item->base_quantity > 0 ? round($cost / $item->base_quantity, 4) : 0;
                $item->save();
            }

            $transfer->status = DocumentStatus::PartiallyProcessed;
            $transfer->total_value = round($totalValue, 4);
            $transfer->shipped_by = Auth::id();
            $transfer->shipped_at = now();
            $transfer->save();

            return $transfer;
        });
    }

    /**
     * Terima barang di gudang tujuan. Kuantitas boleh lebih kecil dari yang
     * dikirim; selisihnya tetap in-transit sampai diselesaikan.
     *
     * @param  array<int, float>  $receivedBaseQuantities  item_id => base qty
     */
    public function receive(StockTransfer $transfer, array $receivedBaseQuantities = []): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $receivedBaseQuantities): StockTransfer {
            if ($transfer->status !== DocumentStatus::PartiallyProcessed) {
                throw new RuntimeException('Transfer belum dikirim atau sudah selesai.');
            }

            $transfer->load('items.product');

            foreach ($transfer->items as $item) {
                $quantity = round(
                    (float) ($receivedBaseQuantities[$item->getKey()] ?? $item->outstandingBaseQuantity()),
                    4,
                );

                if ($quantity <= 0) {
                    continue;
                }

                if ($quantity > $item->outstandingBaseQuantity()) {
                    throw new RuntimeException("Penerimaan {$item->product->sku} melebihi jumlah yang dikirim.");
                }

                $batch = $item->batch_id ? Batch::withoutGlobalScopes()->find($item->batch_id) : null;

                $this->stock->receive(new StockMovement(
                    product: $item->product,
                    warehouseId: (int) $transfer->to_warehouse_id,
                    type: InventoryTransactionType::TransferIn,
                    quantity: $quantity,
                    unitId: (int) $item->product->base_unit_id,
                    unitCost: $item->unit_cost,
                    batchNumber: $batch?->batch_number,
                    expiryDate: $batch?->expiry_date,
                    supplierId: $batch?->supplier_id,
                    documentType: 'stock_transfer',
                    documentId: (int) $transfer->getKey(),
                    documentNumber: $transfer->number,
                    date: $transfer->transfer_date,
                    branchId: $transfer->to_branch_id,
                    note: $item->note,
                ));

                $item->received_base_quantity = round($item->received_base_quantity + $quantity, 4);
                $item->save();
            }

            $outstanding = $transfer->items->sum(fn (StockTransferItem $item) => $item->fresh()->outstandingBaseQuantity());

            $transfer->status = $outstanding > 0 ? DocumentStatus::PartiallyProcessed : DocumentStatus::Completed;
            $transfer->received_by = Auth::id();
            $transfer->received_at = now();
            $transfer->save();

            return $transfer;
        });
    }

    public function cancel(StockTransfer $transfer): StockTransfer
    {
        if (! $transfer->status->isEditable()) {
            throw new RuntimeException('Transfer yang sudah dikirim tidak bisa dibatalkan, gunakan transfer balik.');
        }

        $transfer->status = DocumentStatus::Cancelled;
        $transfer->save();

        return $transfer;
    }

    /**
     * @param  array<int, array{product_id: int, unit_id: int, quantity: float, batch_id?: ?int, note?: ?string}>  $items
     */
    private function syncItems(StockTransfer $transfer, array $items): void
    {
        $transfer->items()->delete();

        foreach ($items as $row) {
            $product = Product::findOrFail($row['product_id']);
            $unitId = (int) ($row['unit_id'] ?: $product->base_unit_id);

            StockTransferItem::create([
                'company_id' => $transfer->company_id,
                'stock_transfer_id' => $transfer->getKey(),
                'product_id' => $product->getKey(),
                'unit_id' => $unitId,
                'batch_id' => $row['batch_id'] ?? null,
                'quantity' => $row['quantity'],
                'base_quantity' => $product->toBaseQuantity((float) $row['quantity'], $unitId),
                'note' => $row['note'] ?? null,
            ]);
        }
    }
}
