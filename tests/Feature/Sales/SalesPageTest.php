<?php

declare(strict_types=1);

use App\Modules\Delivery\Services\DeliveryService;
use App\Modules\Sales\Services\PosService;
use App\Modules\Sales\Services\QuotationService;
use App\Modules\Sales\Services\SalesInvoiceService;
use App\Modules\Sales\Services\SalesOrderService;
use App\Modules\Sales\Services\SalesReturnService;

it('membuka seluruh halaman modul penjualan dan pengiriman', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    $owner = $this->makeUser($company, 'Owner', $branch);

    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);
    $customer = makeCustomer($company->id);

    receiveStock($product, $warehouse->id, 100, 10_000);

    $quotation = app(QuotationService::class)->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'quotation_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 10, 'unit_price' => 20_000],
    ]);

    $orders = app(SalesOrderService::class);

    $order = $orders->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 20, 'unit_price' => 20_000],
    ]);

    $orders->submit($order);

    $deliveries = app(DeliveryService::class);

    $delivery = $deliveries->save(null, [
        'sales_order_id' => $order->id,
        'delivery_date' => now()->toDateString(),
    ], $deliveries->draftItemsFromOrder($order->fresh()));

    $invoice = app(SalesInvoiceService::class)->save(null, [
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'warehouse_id' => $warehouse->id,
        'invoice_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 5, 'unit_price' => 20_000],
    ]);

    $return = app(SalesReturnService::class)->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'return_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 2, 'unit_price' => 20_000],
    ]);

    $pages = [
        route('sales.quotations.index'),
        route('sales.quotations.create'),
        route('sales.quotations.edit', $quotation),
        route('sales.quotations.detail', $quotation),
        route('sales.orders.index'),
        route('sales.orders.create'),
        route('sales.orders.detail', $order),
        route('delivery.orders.index'),
        route('delivery.orders.create'),
        route('delivery.orders.edit', $delivery),
        route('delivery.orders.detail', $delivery),
        route('delivery.orders.print', $delivery),
        route('delivery.picking.index'),
        route('delivery.picking.pick', $delivery),
        route('sales.invoices.index'),
        route('sales.invoices.create'),
        route('sales.invoices.edit', $invoice),
        route('sales.invoices.detail', $invoice),
        route('sales.invoices.print', $invoice),
        route('sales.returns.index'),
        route('sales.returns.create'),
        route('sales.returns.edit', $return),
        route('sales.returns.detail', $return),
    ];

    foreach ($pages as $page) {
        $this->actingAs($owner)->get($page)->assertOk();
    }
});

it('menyajikan data DataTables server-side untuk dokumen penjualan', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    $owner = $this->makeUser($company, 'Owner', $branch);

    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);
    $customer = makeCustomer($company->id);

    app(SalesOrderService::class)->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 5, 'unit_price' => 20_000],
    ]);

    $endpoints = [
        route('sales.quotations.data'),
        route('sales.orders.data'),
        route('sales.invoices.data'),
        route('sales.returns.data'),
        route('delivery.orders.data'),
        route('delivery.picking.data'),
    ];

    foreach ($endpoints as $endpoint) {
        $this->actingAs($owner)
            ->post($endpoint, ['draw' => 1, 'start' => 0, 'length' => 10])
            ->assertOk()
            ->assertJsonPath('draw', 1);
    }

    $this->actingAs($owner)
        ->post(route('sales.orders.data'), ['draw' => 1, 'start' => 0, 'length' => 10])
        ->assertJsonPath('recordsTotal', 1);
});

it('membuka halaman kasir dan mengelola shift', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    $cashier = $this->makeUser($company, 'Cashier', $branch);

    // Tanpa shift terbuka, kasir diarahkan untuk membuka shift lebih dulu.
    $this->actingAs($cashier)->get(route('pos.index'))->assertOk()->assertSee('Belum ada shift terbuka');

    $this->actingAs($cashier)
        ->post(route('pos.shift.open'), ['warehouse_id' => $warehouse->id, 'opening_cash' => 250_000])
        ->assertRedirect(route('pos.index'));

    $shift = app(PosService::class)->activeShift($cashier->fresh());

    expect($shift)->not->toBeNull();

    $this->actingAs($cashier)->get(route('pos.index'))->assertOk();
    $this->actingAs($cashier)->get(route('pos.shift'))->assertOk();
    $this->actingAs($cashier)->get(route('pos.shift.detail', $shift))->assertOk();

    $this->actingAs($cashier)
        ->post(route('pos.shift.cash', $shift), ['amount' => 50_000, 'direction' => 'out', 'note' => 'Setor ke finance'])
        ->assertRedirect();

    expect($shift->fresh()->expected_cash)->toBe(200_000.0);

    $this->actingAs($cashier)
        ->post(route('pos.shift.close', $shift), ['counted_cash' => 200_000])
        ->assertRedirect(route('pos.shift'));

    expect($shift->fresh()->status)->toBe('closed');
    expect($shift->fresh()->difference)->toBe(0.0);
});

it('menolak akses penjualan untuk user tanpa permission', function (): void {
    ['company' => $company, 'branch' => $branch] = $this->bootCompany();
    $warehouseStaff = $this->makeUser($company, 'Warehouse Staff', $branch);

    $this->actingAs($warehouseStaff)->get(route('sales.orders.create'))->assertForbidden();
    $this->actingAs($warehouseStaff)->get(route('sales.returns.create'))->assertForbidden();
    $this->actingAs($warehouseStaff)->get(route('pos.index'))->assertForbidden();
});
