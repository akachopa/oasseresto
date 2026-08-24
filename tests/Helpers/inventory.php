<?php

declare(strict_types=1);

use App\Modules\Core\Enums\InventoryTransactionType;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Inventory\Services\StockMovement;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\Unit;
use Illuminate\Support\Carbon;

/**
 * Produk uji dengan satuan dasar PCS dan satuan turunan DUS (1 dus = 12 pcs).
 *
 * @return array{product: Product, pcs: Unit, dus: Unit}
 */
function stockProduct(int $companyId, bool $trackBatch = false): array
{
    $pcs = Unit::where('code', 'PCS')->firstOrFail();
    $dus = Unit::where('code', 'DUS')->firstOrFail();

    $product = Product::create([
        'company_id' => $companyId,
        'sku' => 'STK-'.str()->random(5),
        'name' => 'Produk Stok',
        'base_unit_id' => $pcs->id,
        'base_price' => 20_000,
        'average_cost' => 10_000,
        'track_batch' => $trackBatch,
    ]);

    $product->units()->create([
        'company_id' => $companyId,
        'unit_id' => $pcs->id,
        'conversion_to_base' => 1,
        'is_base' => true,
    ]);

    $product->units()->create([
        'company_id' => $companyId,
        'unit_id' => $dus->id,
        'conversion_to_base' => 12,
        'is_base' => false,
    ]);

    return ['product' => $product, 'pcs' => $pcs, 'dus' => $dus];
}

/**
 * @return array<int, StockLedger>
 */
function receiveStock(
    Product $product,
    int $warehouseId,
    float $quantity,
    float $unitCost,
    ?int $unitId = null,
    ?string $batchNumber = null,
    ?string $expiry = null,
): array {
    return app(StockService::class)->receive(new StockMovement(
        product: $product,
        warehouseId: $warehouseId,
        type: InventoryTransactionType::Purchase,
        quantity: $quantity,
        unitId: $unitId ?? (int) $product->base_unit_id,
        unitCost: $unitCost,
        batchNumber: $batchNumber,
        expiryDate: $expiry ? Carbon::parse($expiry) : null,
    ));
}

/**
 * @return array<int, StockLedger>
 */
function issueStock(Product $product, int $warehouseId, float $quantity, ?int $unitId = null): array
{
    return app(StockService::class)->issue(new StockMovement(
        product: $product,
        warehouseId: $warehouseId,
        type: InventoryTransactionType::Sale,
        quantity: $quantity,
        unitId: $unitId ?? (int) $product->base_unit_id,
    ));
}
