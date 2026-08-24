<?php

declare(strict_types=1);

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\CostingMethod;
use App\Modules\Core\Enums\InventoryTransactionType;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Inventory\Services\StockMovement;
use App\Modules\Inventory\Services\StockService;

it('menghitung harga pokok pengeluaran dengan FIFO per layer penerimaan', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    $company->update(['costing_method' => CostingMethod::Fifo]);

    ['product' => $product] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 10, 10_000);
    receiveStock($product, $warehouse->id, 10, 12_000);

    $ledgers = issueStock($product, $warehouse->id, 15);

    // 10 pcs dari layer 10.000 dan 5 pcs dari layer 12.000.
    expect(collect($ledgers)->sum('total_cost'))->toBe(160_000.0);
    expect(app(StockService::class)->onHand($product->id, $warehouse->id))->toBe(5.0);
});

it('menghitung harga pokok pengeluaran dengan rata-rata tertimbang', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    $company->update(['costing_method' => CostingMethod::WeightedAverage]);

    ['product' => $product] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 10, 10_000);
    receiveStock($product, $warehouse->id, 10, 12_000);

    $balance = app(StockService::class)->balance($product->id, $warehouse->id);
    expect($balance->average_cost)->toBe(11_000.0);

    $ledgers = issueStock($product, $warehouse->id, 15);

    expect(collect($ledgers)->sum('total_cost'))->toBe(165_000.0);
});

it('mengkonversi kuantitas dokumen ke satuan dasar pada kartu stok', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'dus' => $dus] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 2, 9_000, $dus->id);

    $ledger = StockLedger::where('product_id', $product->id)->firstOrFail();

    expect($ledger->base_quantity)->toBe(24.0);
    expect($ledger->quantity)->toBe(2.0);
    expect(app(StockService::class)->onHand($product->id, $warehouse->id))->toBe(24.0);
});

it('mengeluarkan batch paling dekat kedaluwarsa lebih dulu', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product] = stockProduct($company->id, trackBatch: true);

    receiveStock($product, $warehouse->id, 10, 10_000, batchNumber: 'LOT-LAMA', expiry: now()->addMonths(6)->toDateString());
    receiveStock($product, $warehouse->id, 10, 11_000, batchNumber: 'LOT-DEKAT', expiry: now()->addMonth()->toDateString());

    issueStock($product, $warehouse->id, 12);

    $near = Batch::where('batch_number', 'LOT-DEKAT')->firstOrFail();
    $far = Batch::where('batch_number', 'LOT-LAMA')->firstOrFail();

    expect($near->quantity)->toBe(0.0);
    expect($far->quantity)->toBe(8.0);
});

it('menolak pengeluaran melebihi saldo bila stok negatif tidak diizinkan', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 5, 10_000);

    expect(fn () => issueStock($product, $warehouse->id, 8))
        ->toThrow(InsufficientStockException::class);

    expect(app(StockService::class)->onHand($product->id, $warehouse->id))->toBe(5.0);
});

it('mengizinkan stok negatif bila flag company diaktifkan', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    $company->update(['allow_negative_stock' => true]);

    ['product' => $product] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 5, 10_000);
    issueStock($product, $warehouse->id, 8);

    expect(app(StockService::class)->onHand($product->id, $warehouse->id))->toBe(-3.0);
});

it('menahan stok yang direservasi agar tidak dijanjikan dua kali', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product] = stockProduct($company->id);

    $stock = app(StockService::class);

    receiveStock($product, $warehouse->id, 20, 10_000);
    $stock->reserve($product->id, $warehouse->id, 8);

    expect($stock->available($product->id, $warehouse->id))->toBe(12.0);

    $stock->release($product->id, $warehouse->id, 8);

    expect($stock->available($product->id, $warehouse->id))->toBe(20.0);
});

it('memindahkan stok antar gudang beserta harga pokoknya', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product] = stockProduct($company->id);

    $outlet = Warehouse::where('code', 'GO-BKL')->firstOrFail();

    receiveStock($product, $warehouse->id, 20, 10_000);

    app(StockService::class)->move(new StockMovement(
        product: $product,
        warehouseId: $warehouse->id,
        type: InventoryTransactionType::TransferOut,
        quantity: 6,
        unitId: (int) $product->base_unit_id,
    ), $outlet->id);

    $stock = app(StockService::class);

    expect($stock->onHand($product->id, $warehouse->id))->toBe(14.0);
    expect($stock->onHand($product->id, $outlet->id))->toBe(6.0);
    expect($stock->balance($product->id, $outlet->id)->average_cost)->toBe(10_000.0);
});
