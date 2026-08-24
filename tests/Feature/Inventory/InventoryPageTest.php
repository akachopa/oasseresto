<?php

declare(strict_types=1);

use App\Modules\Company\Models\Warehouse;
use App\Modules\Inventory\Services\StockAdjustmentService;
use App\Modules\Inventory\Services\StockOpnameService;
use App\Modules\Inventory\Services\StockTransferService;

it('membuka seluruh halaman modul persediaan', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    $owner = $this->makeUser($company, 'Owner', $branch);

    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id, trackBatch: true);
    $outlet = Warehouse::where('code', 'GO-BKL')->firstOrFail();

    receiveStock($product, $warehouse->id, 50, 10_000, batchNumber: 'LOT-A', expiry: now()->addMonths(3)->toDateString());

    $transfer = app(StockTransferService::class)->save(null, [
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $outlet->id,
        'transfer_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 5],
    ]);

    $adjustment = app(StockAdjustmentService::class)->save(null, [
        'warehouse_id' => $warehouse->id,
        'adjustment_date' => now()->toDateString(),
        'reason' => 'damaged',
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => -2],
    ]);

    $opname = app(StockOpnameService::class)->create([
        'warehouse_id' => $warehouse->id,
        'opname_date' => now()->toDateString(),
        'scope_type' => 'full',
    ]);

    $pages = [
        route('inventory.stock.index'),
        route('inventory.stock.detail', $product),
        route('inventory.ledger.index'),
        route('inventory.batches.index'),
        route('inventory.transfers.index'),
        route('inventory.transfers.create'),
        route('inventory.transfers.edit', $transfer),
        route('inventory.transfers.detail', $transfer),
        route('inventory.adjustments.index'),
        route('inventory.adjustments.create'),
        route('inventory.adjustments.edit', $adjustment),
        route('inventory.adjustments.detail', $adjustment),
        route('inventory.opnames.index'),
        route('inventory.opnames.create'),
        route('inventory.opnames.count', $opname),
        route('inventory.opnames.detail', $opname),
    ];

    foreach ($pages as $page) {
        $this->actingAs($owner)->get($page)->assertOk();
    }
});

it('menyajikan data DataTables server-side untuk saldo stok dan dokumen', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    $owner = $this->makeUser($company, 'Owner', $branch);

    ['product' => $product] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 25, 10_000);

    $endpoints = [
        route('inventory.stock.data'),
        route('inventory.ledger.data'),
        route('inventory.transfers.data'),
        route('inventory.adjustments.data'),
        route('inventory.opnames.data'),
    ];

    foreach ($endpoints as $endpoint) {
        $this->actingAs($owner)
            ->post($endpoint, ['draw' => 1, 'start' => 0, 'length' => 10])
            ->assertOk()
            ->assertJsonPath('draw', 1);
    }

    $this->actingAs($owner)
        ->post(route('inventory.stock.data'), ['draw' => 1, 'start' => 0, 'length' => 10])
        ->assertJsonPath('recordsTotal', 1);
});

it('menolak akses persediaan untuk user tanpa permission', function (): void {
    ['company' => $company, 'branch' => $branch] = $this->bootCompany();
    $cashier = $this->makeUser($company, 'Cashier', $branch);

    $this->actingAs($cashier)->get(route('inventory.adjustments.create'))->assertForbidden();
    $this->actingAs($cashier)->get(route('inventory.opnames.create'))->assertForbidden();
});
