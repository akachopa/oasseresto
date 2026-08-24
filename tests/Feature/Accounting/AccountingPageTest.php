<?php

declare(strict_types=1);

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Purchase\Services\GoodsReceiptService;
use App\Modules\Purchase\Services\PurchaseOrderService;

it('membuka seluruh halaman modul akuntansi', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    $owner = $this->makeUser($company, 'Owner', $branch);

    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);
    $supplier = makeSupplier($company->id);
    $orders = app(PurchaseOrderService::class);
    $receipts = app(GoodsReceiptService::class);

    $order = $orders->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 4, 'unit_price' => 9_000],
    ]);
    $orders->submit($order);

    $receipt = $receipts->save(null, [
        'purchase_order_id' => $order->id,
        'receipt_date' => now()->toDateString(),
        'warehouse_id' => $warehouse->id,
    ], $receipts->draftItemsFromOrder($order->fresh()));
    $receipts->post($receipt);

    $journal = JournalEntry::firstOrFail();
    $account = Account::postable()->orderBy('code')->firstOrFail();

    $pages = [
        route('home.accounting'),
        route('accounting.accounts.index'),
        route('accounting.accounts.create'),
        route('accounting.accounts.edit', $account->id),
        route('accounting.journals.index'),
        route('accounting.journals.create'),
        route('accounting.journals.detail', $journal),
        route('accounting.ledger'),
        route('accounting.reports.trial-balance'),
        route('accounting.reports.profit-loss'),
        route('accounting.reports.balance-sheet'),
        route('accounting.reports.cash-flow'),
        route('accounting.periods.index'),
    ];

    foreach ($pages as $page) {
        $this->actingAs($owner)->get($page)->assertOk();
    }
});

it('menyajikan data DataTables server-side untuk akun dan jurnal', function (): void {
    ['company' => $company, 'branch' => $branch] = $this->bootCompany();
    $owner = $this->makeUser($company, 'Owner', $branch);

    $this->actingAs($owner)
        ->post(route('accounting.accounts.data'), ['draw' => 1, 'start' => 0, 'length' => 10])
        ->assertOk()
        ->assertJsonPath('draw', 1);

    $this->actingAs($owner)
        ->post(route('accounting.journals.data'), ['draw' => 1, 'start' => 0, 'length' => 10])
        ->assertOk()
        ->assertJsonPath('draw', 1);
});

it('menolak akses akuntansi untuk user tanpa permission', function (): void {
    ['company' => $company, 'branch' => $branch] = $this->bootCompany();
    $salesman = $this->makeUser($company, 'Salesman', $branch);

    $this->actingAs($salesman)->get(route('accounting.accounts.index'))->assertForbidden();
    $this->actingAs($salesman)->get(route('accounting.journals.index'))->assertForbidden();
    $this->actingAs($salesman)->get(route('accounting.reports.trial-balance'))->assertForbidden();
});
