<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Core\Enums\InventoryTransactionType;
use App\Modules\Product\Models\Product;
use Illuminate\Support\Carbon;

/**
 * Satu permintaan pergerakan stok. Kuantitas dinyatakan dalam satuan yang
 * dipakai dokumen; StockService yang mengonversinya ke satuan dasar (PLAN 24).
 */
readonly class StockMovement
{
    public function __construct(
        public Product $product,
        public int $warehouseId,
        public InventoryTransactionType $type,
        public float $quantity,
        public ?int $unitId = null,
        public ?float $unitCost = null,
        public ?int $batchId = null,
        public ?string $batchNumber = null,
        public ?Carbon $expiryDate = null,
        public ?int $supplierId = null,
        public ?string $documentType = null,
        public ?int $documentId = null,
        public ?string $documentNumber = null,
        public ?Carbon $date = null,
        public ?int $branchId = null,
        public ?string $note = null,
    ) {}

    public function unitIdOrBase(): int
    {
        return $this->unitId ?? (int) $this->product->base_unit_id;
    }

    public function baseQuantity(): float
    {
        return $this->product->toBaseQuantity(abs($this->quantity), $this->unitIdOrBase());
    }

    public function occurredAt(): Carbon
    {
        return $this->date?->copy() ?? Carbon::now();
    }

    /**
     * Salinan dengan kuantitas dan batch berbeda, dipakai saat satu permintaan
     * pengeluaran harus dipecah ke beberapa batch.
     */
    public function withBatch(?int $batchId, float $baseQuantity): self
    {
        return new self(
            product: $this->product,
            warehouseId: $this->warehouseId,
            type: $this->type,
            quantity: $baseQuantity,
            unitId: (int) $this->product->base_unit_id,
            unitCost: $this->unitCost,
            batchId: $batchId,
            batchNumber: $this->batchNumber,
            expiryDate: $this->expiryDate,
            supplierId: $this->supplierId,
            documentType: $this->documentType,
            documentId: $this->documentId,
            documentNumber: $this->documentNumber,
            date: $this->date,
            branchId: $this->branchId,
            note: $this->note,
        );
    }
}
