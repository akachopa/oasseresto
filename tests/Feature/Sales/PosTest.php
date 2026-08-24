<?php

declare(strict_types=1);

use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Services\PosService;

it('menjual tunai lewat kasir dan menutup shift dengan selisih kas', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 100, 10_000);

    $cashier = $this->makeUser($company, 'Cashier', $branch);
    $customer = makeCustomer($company->id, ['payment_term' => 'cash']);

    $pos = app(PosService::class);

    $shift = $pos->openShift($cashier, $warehouse->id, 500_000);

    $this->actingAs($cashier);

    $invoice = $pos->checkout($shift, $customer, [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 3, 'unit_price' => 20_000],
    ], 100_000);

    expect($invoice->total)->toBe(60_000.0);
    expect($invoice->status)->toBe(DocumentStatus::Posted);
    expect($invoice->source)->toBe('pos');
    expect($invoice->cost_of_goods)->toBe(30_000.0);
    expect($pos->change($invoice, 100_000))->toBe(40_000.0);
    expect(app(StockService::class)->onHand($product->id, $warehouse->id))->toBe(97.0);
    expect(Receivable::where('document_id', $invoice->id)->exists())->toBeFalse();

    $shift->refresh();

    expect($shift->cash_sales)->toBe(60_000.0);
    expect($shift->transaction_count)->toBe(1);
    expect($shift->expected_cash)->toBe(560_000.0);

    $pos->closeShift($shift, 555_000, 'Selisih kurang karena uang kecil.');

    $shift->refresh();

    expect($shift->status)->toBe('closed');
    expect($shift->difference)->toBe(-5_000.0);
});

it('menolak shift kedua untuk kasir yang sama', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();

    $cashier = $this->makeUser($company, 'Cashier', $branch);
    $pos = app(PosService::class);

    $pos->openShift($cashier, $warehouse->id, 0);

    expect(fn () => $pos->openShift($cashier, $warehouse->id, 0))->toThrow(RuntimeException::class);
});

it('menolak pembayaran kurang untuk customer tunai', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 20, 10_000);

    $cashier = $this->makeUser($company, 'Cashier', $branch);
    $customer = makeCustomer($company->id, ['payment_term' => 'cash']);

    $pos = app(PosService::class);
    $shift = $pos->openShift($cashier, $warehouse->id, 0);

    $this->actingAs($cashier);

    expect(fn () => $pos->checkout($shift, $customer, [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 2, 'unit_price' => 20_000],
    ], 10_000))->toThrow(RuntimeException::class);

    expect(app(StockService::class)->onHand($product->id, $warehouse->id))->toBe(20.0);
});

it('mencatat sisa tagihan kasir sebagai piutang untuk customer bertermin', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 50, 10_000);

    $cashier = $this->makeUser($company, 'Cashier', $branch);
    $customer = makeCustomer($company->id, ['payment_term' => 'net_14']);

    $pos = app(PosService::class);
    $shift = $pos->openShift($cashier, $warehouse->id, 0);

    $this->actingAs($cashier);

    $invoice = $pos->checkout($shift, $customer, [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 10, 'unit_price' => 20_000],
    ], 50_000);

    $receivable = Receivable::where('document_id', $invoice->id)->firstOrFail();

    expect($invoice->total)->toBe(200_000.0);
    expect($invoice->paid_amount)->toBe(50_000.0);
    expect($receivable->outstanding_amount)->toBe(150_000.0);

    $shift->refresh();

    expect($shift->cash_sales)->toBe(50_000.0);
    expect($shift->credit_sales)->toBe(150_000.0);
});
