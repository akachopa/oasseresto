<?php

declare(strict_types=1);

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockOpname;
use App\Modules\Inventory\Services\StockAdjustmentService;
use App\Modules\Inventory\Services\StockOpnameService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Inventory\Services\StockTransferService;

it('menahan barang sebagai in-transit sampai penerimaan selesai', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $outlet = Warehouse::where('code', 'GO-BKL')->firstOrFail();

    receiveStock($product, $warehouse->id, 30, 10_000);

    $transfers = app(StockTransferService::class);

    $transfer = $transfers->save(null, [
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $outlet->id,
        'transfer_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 10],
    ]);

    $transfers->ship($transfer);

    $stock = app(StockService::class);

    expect($transfer->fresh()->status)->toBe(DocumentStatus::PartiallyProcessed);
    expect($stock->onHand($product->id, $warehouse->id))->toBe(20.0);
    expect($stock->onHand($product->id, $outlet->id))->toBe(0.0);

    $itemId = $transfer->items()->value('id');

    // Penerimaan sebagian: sisanya tetap in-transit.
    $transfers->receive($transfer->fresh(), [$itemId => 6.0]);

    expect($transfer->fresh()->status)->toBe(DocumentStatus::PartiallyProcessed);
    expect($stock->onHand($product->id, $outlet->id))->toBe(6.0);

    $transfers->receive($transfer->fresh());

    expect($transfer->fresh()->status)->toBe(DocumentStatus::Completed);
    expect($stock->onHand($product->id, $outlet->id))->toBe(10.0);
});

it('menolak penerimaan transfer melebihi jumlah yang dikirim', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $outlet = Warehouse::where('code', 'GO-BKL')->firstOrFail();

    receiveStock($product, $warehouse->id, 30, 10_000);

    $transfers = app(StockTransferService::class);

    $transfer = $transfers->save(null, [
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $outlet->id,
        'transfer_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 5],
    ]);

    $transfers->ship($transfer);

    $itemId = $transfer->items()->value('id');

    expect(fn () => $transfers->receive($transfer->fresh(), [$itemId => 9.0]))
        ->toThrow(RuntimeException::class);
});

it('memposting penyesuaian minus sebagai pengurangan stok bernilai negatif', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 20, 10_000);

    $adjustments = app(StockAdjustmentService::class);

    $adjustment = $adjustments->save(null, [
        'warehouse_id' => $warehouse->id,
        'adjustment_date' => now()->toDateString(),
        'reason' => 'damaged',
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => -3],
    ]);

    $adjustments->post($adjustment);

    $adjustment->refresh();

    expect($adjustment->status)->toBe(DocumentStatus::Posted);
    expect($adjustment->total_value)->toBe(-30_000.0);
    expect(app(StockService::class)->onHand($product->id, $warehouse->id))->toBe(17.0);
});

it('menolak perubahan penyesuaian yang sudah diposting', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 20, 10_000);

    $adjustments = app(StockAdjustmentService::class);

    $adjustment = $adjustments->save(null, [
        'warehouse_id' => $warehouse->id,
        'adjustment_date' => now()->toDateString(),
        'reason' => 'correction',
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 2],
    ]);

    $adjustments->post($adjustment);

    expect(fn () => $adjustments->save($adjustment->fresh(), [
        'warehouse_id' => $warehouse->id,
        'adjustment_date' => now()->toDateString(),
        'reason' => 'correction',
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 5],
    ]))->toThrow(RuntimeException::class);
});

it('mencatat konversi satuan pada penyesuaian dus ke pcs', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'dus' => $dus] = stockProduct($company->id);

    $adjustments = app(StockAdjustmentService::class);

    $adjustment = $adjustments->save(null, [
        'warehouse_id' => $warehouse->id,
        'adjustment_date' => now()->toDateString(),
        'reason' => 'opening',
    ], [
        ['product_id' => $product->id, 'unit_id' => $dus->id, 'quantity' => 3, 'unit_cost' => 9_000],
    ]);

    $adjustments->post($adjustment);

    expect(app(StockService::class)->onHand($product->id, $warehouse->id))->toBe(36.0);
});

it('membuat lembar hitung opname dari saldo gudang', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 40, 10_000);

    $opname = app(StockOpnameService::class)->create([
        'warehouse_id' => $warehouse->id,
        'opname_date' => now()->toDateString(),
        'scope_type' => 'full',
    ]);

    expect($opname->items)->toHaveCount(1);
    expect($opname->items->first()->system_base_quantity)->toBe(40.0);
    expect($opname->items->first()->unit_cost)->toBe(10_000.0);
});

it('menulis selisih opname ke kartu stok saat diposting', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 40, 10_000);

    $opnames = app(StockOpnameService::class);

    $opname = $opnames->create([
        'warehouse_id' => $warehouse->id,
        'opname_date' => now()->toDateString(),
        'scope_type' => 'full',
    ]);

    $item = $opname->items->first();

    $opnames->saveCounts($opname, [
        ['item_id' => $item->id, 'counted_base_quantity' => 37.0],
    ]);

    $opnames->post($opname->fresh());

    $opname->refresh();

    expect($opname->status)->toBe(DocumentStatus::Posted);
    expect($opname->difference_count)->toBe(1);
    expect($opname->difference_value)->toBe(-30_000.0);
    expect(app(StockService::class)->onHand($product->id, $warehouse->id))->toBe(37.0);
});

it('menolak posting opname yang masih punya baris belum dihitung', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 10, 10_000);

    $opnames = app(StockOpnameService::class);

    $opname = $opnames->create([
        'warehouse_id' => $warehouse->id,
        'opname_date' => now()->toDateString(),
        'scope_type' => 'full',
    ]);

    expect(fn () => $opnames->post($opname))->toThrow(RuntimeException::class);
    expect($opname->fresh()->status)->toBe(DocumentStatus::Draft);
});

it('memberi nomor dokumen unik per company dan jenis dokumen', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $adjustments = app(StockAdjustmentService::class);

    $numbers = collect(range(1, 3))->map(fn () => $adjustments->save(null, [
        'warehouse_id' => $warehouse->id,
        'adjustment_date' => now()->toDateString(),
        'reason' => 'correction',
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 1],
    ])->number);

    expect($numbers->unique())->toHaveCount(3);
    expect(StockAdjustment::count())->toBe(3);
    expect(StockOpname::count())->toBe(0);
});
