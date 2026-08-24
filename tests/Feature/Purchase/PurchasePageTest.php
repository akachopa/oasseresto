<?php

declare(strict_types=1);

use App\Modules\Purchase\Models\PurchaseRequest;
use App\Modules\Purchase\Services\GoodsReceiptService;
use App\Modules\Purchase\Services\PurchaseInvoiceService;
use App\Modules\Purchase\Services\PurchaseOrderService;
use App\Modules\Purchase\Services\PurchaseRequestService;
use App\Modules\Purchase\Services\PurchaseReturnService;

it('membuka seluruh halaman modul pembelian', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    $owner = $this->makeUser($company, 'Owner', $branch);

    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);
    $supplier = makeSupplier($company->id);

    $product->update(['reorder_point' => 50, 'minimum_stock' => 20, 'maximum_stock' => 200]);

    $requests = app(PurchaseRequestService::class);
    $orders = app(PurchaseOrderService::class);
    $receipts = app(GoodsReceiptService::class);
    $invoices = app(PurchaseInvoiceService::class);
    $returns = app(PurchaseReturnService::class);

    $request = $requests->save(null, [
        'warehouse_id' => $warehouse->id,
        'request_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 50, 'estimated_price' => 10_000],
    ]);

    $order = $orders->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 50, 'unit_price' => 10_000],
    ]);

    $draftReceipt = $receipts->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'receipt_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 10, 'unit_cost' => 10_000],
    ]);

    $invoice = $invoices->save(null, [
        'supplier_id' => $supplier->id,
        'branch_id' => $branch->id,
        'invoice_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 10, 'unit_price' => 10_000],
    ]);

    $return = $returns->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'return_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 2, 'unit_price' => 10_000],
    ]);

    $pages = [
        route('purchase.reorder.index'),
        route('purchase.requests.index'),
        route('purchase.requests.create'),
        route('purchase.requests.edit', $request),
        route('purchase.requests.detail', $request),
        route('purchase.orders.index'),
        route('purchase.orders.create'),
        route('purchase.orders.edit', $order),
        route('purchase.orders.detail', $order),
        route('purchase.orders.print', $order),
        route('purchase.receipts.index'),
        route('purchase.receipts.create'),
        route('purchase.receipts.edit', $draftReceipt),
        route('purchase.receipts.detail', $draftReceipt),
        route('purchase.invoices.index'),
        route('purchase.invoices.create'),
        route('purchase.invoices.edit', $invoice),
        route('purchase.invoices.detail', $invoice),
        route('purchase.returns.index'),
        route('purchase.returns.create'),
        route('purchase.returns.edit', $return),
        route('purchase.returns.detail', $return),
    ];

    foreach ($pages as $page) {
        $this->actingAs($owner)->get($page)->assertOk();
    }
});

it('menyajikan data DataTables server-side untuk dokumen pembelian', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    $owner = $this->makeUser($company, 'Owner', $branch);

    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);
    $supplier = makeSupplier($company->id);

    $product->update(['reorder_point' => 50, 'minimum_stock' => 20, 'maximum_stock' => 200]);

    app(PurchaseOrderService::class)->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 10, 'unit_price' => 10_000],
    ]);

    $endpoints = [
        route('purchase.reorder.data'),
        route('purchase.requests.data'),
        route('purchase.orders.data'),
        route('purchase.receipts.data'),
        route('purchase.invoices.data'),
        route('purchase.returns.data'),
    ];

    foreach ($endpoints as $endpoint) {
        $this->actingAs($owner)
            ->post($endpoint, ['draw' => 1, 'start' => 0, 'length' => 10])
            ->assertOk()
            ->assertJsonPath('draw', 1);
    }

    $this->actingAs($owner)
        ->post(route('purchase.orders.data'), ['draw' => 1, 'start' => 0, 'length' => 10])
        ->assertJsonPath('recordsTotal', 1);

    $this->actingAs($owner)
        ->post(route('purchase.reorder.data'), ['draw' => 1, 'start' => 0, 'length' => 10])
        ->assertJsonPath('recordsTotal', 1);
});

it('membuat purchase request draft dari rekomendasi reorder', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    $purchasing = $this->makeUser($company, 'Purchasing', $branch);

    ['product' => $product] = stockProduct($company->id);

    $product->update([
        'reorder_point' => 50,
        'minimum_stock' => 20,
        'maximum_stock' => 200,
        'last_purchase_cost' => 10_000,
    ]);

    $this->actingAs($purchasing)
        ->post(route('purchase.reorder.store'), ['warehouse_id' => $warehouse->id])
        ->assertRedirect();

    $request = PurchaseRequest::firstOrFail();

    expect($request->items)->toHaveCount(1);
    expect($request->items->first()->quantity)->toBe(200.0);
    expect($request->estimated_total)->toBe(2_000_000.0);
});

it('menolak akses pembelian untuk user tanpa permission', function (): void {
    ['company' => $company, 'branch' => $branch] = $this->bootCompany();
    $cashier = $this->makeUser($company, 'Cashier', $branch);

    $this->actingAs($cashier)->get(route('purchase.orders.index'))->assertForbidden();
    $this->actingAs($cashier)->get(route('purchase.receipts.create'))->assertForbidden();
});
