<?php

declare(strict_types=1);

use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\PayableStatus;
use App\Modules\Core\Enums\ReceivableStatus;
use App\Modules\Finance\Models\CashTransaction;
use App\Modules\Finance\Models\Payable;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Finance\Services\AgingService;
use App\Modules\Finance\Services\CashService;
use App\Modules\Finance\Services\PaymentAllocationService;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Finance\Services\ReceiptService;
use App\Modules\Purchase\Services\GoodsReceiptService;
use App\Modules\Purchase\Services\PurchaseInvoiceService;
use App\Modules\Purchase\Services\PurchaseOrderService;

it('melunasi piutang lewat penerimaan dan menambah saldo kas', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();

    ['receivable' => $receivable, 'invoice' => $invoice, 'customer' => $customer] =
        buildReceivable($company->id, $warehouse->id, $branch->id);

    $account = makeCashAccount($company->id);
    $receipts = app(ReceiptService::class);

    $receipt = $receipts->save(null, [
        'customer_id' => $customer->id,
        'cash_account_id' => $account->id,
        'receipt_date' => now()->toDateString(),
        'method' => 'transfer',
        'amount' => 250_000,
        'reference' => 'BCA-0001',
    ], [
        ['target_id' => $receivable->id, 'amount' => 250_000],
    ]);

    expect($receipt->allocated_amount)->toBe(250_000.0);

    $receipts->post($receipt);

    $receivable->refresh();

    expect($receivable->paid_amount)->toBe(250_000.0);
    expect($receivable->status)->toBe(ReceivableStatus::PartiallyPaid);
    expect($invoice->fresh()->outstanding_amount)->toBe(round($invoice->total - 250_000, 4));
    expect($account->fresh()->balance)->toBe(250_000.0);
    expect($customer->fresh()->outstanding_amount)->toBe(round($invoice->total - 250_000, 4));
});

it('menandai piutang lunas saat penerimaan menutup seluruh sisa', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();

    ['receivable' => $receivable, 'invoice' => $invoice, 'customer' => $customer] =
        buildReceivable($company->id, $warehouse->id, $branch->id);

    $account = makeCashAccount($company->id);
    $receipts = app(ReceiptService::class);

    $receipt = $receipts->save(null, [
        'customer_id' => $customer->id,
        'cash_account_id' => $account->id,
        'receipt_date' => now()->toDateString(),
        'amount' => $invoice->total,
    ], [
        ['target_id' => $receivable->id, 'amount' => $invoice->total],
    ]);

    $receipts->post($receipt);

    expect($receivable->fresh()->status)->toBe(ReceivableStatus::Paid);
    expect($receivable->fresh()->outstanding_amount)->toBe(0.0);
    expect($customer->fresh()->outstanding_amount)->toBe(0.0);
});

it('menolak alokasi yang melebihi sisa piutang', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();

    ['receivable' => $receivable, 'customer' => $customer] =
        buildReceivable($company->id, $warehouse->id, $branch->id);

    $account = makeCashAccount($company->id);

    expect(fn () => app(ReceiptService::class)->save(null, [
        'customer_id' => $customer->id,
        'cash_account_id' => $account->id,
        'receipt_date' => now()->toDateString(),
        'amount' => 900_000,
    ], [
        ['target_id' => $receivable->id, 'amount' => 900_000],
    ]))->toThrow(RuntimeException::class);
});

it('memberi diskon pelunasan dini dan mengurangi nilai piutang', function (): void {
    config()->set('oasse.credit.early_payment', ['percent' => 2, 'within_days' => 10]);

    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();

    ['receivable' => $receivable, 'customer' => $customer] =
        buildReceivable($company->id, $warehouse->id, $branch->id);

    $allocations = app(PaymentAllocationService::class);

    // Pembayaran sebagian tidak melunasi tagihan, jadi diskon belum berlaku.
    $partial = $allocations->suggestForCustomer($customer, 100_000);

    expect($partial)->toHaveCount(1);
    expect($partial[0]['discount_amount'])->toBe(0.0);

    // Membayar seluruh sisa dalam tenggang diskon: potongan 2% berlaku.
    $full = $allocations->suggestForCustomer($customer, $receivable->outstanding_amount);

    expect($full[0]['discount_amount'])->toBe(round($receivable->outstanding_amount * 0.02, 4));

    $account = makeCashAccount($company->id);

    $receipt = app(ReceiptService::class)->save(null, [
        'customer_id' => $customer->id,
        'cash_account_id' => $account->id,
        'receipt_date' => now()->toDateString(),
        'amount' => $full[0]['amount'],
    ], [
        ['target_id' => $receivable->id, 'amount' => $full[0]['amount'], 'discount_amount' => $full[0]['discount_amount']],
    ]);

    expect($receipt->discount_amount)->toBe($full[0]['discount_amount']);
    expect($receivable->fresh()->status)->toBe(ReceivableStatus::Paid);
    expect($receivable->fresh()->outstanding_amount)->toBe(0.0);
});

it('mengembalikan piutang saat penerimaan yang sudah diposting dibatalkan', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();

    ['receivable' => $receivable, 'customer' => $customer] =
        buildReceivable($company->id, $warehouse->id, $branch->id);

    $account = makeCashAccount($company->id);
    $receipts = app(ReceiptService::class);

    $receipt = $receipts->save(null, [
        'customer_id' => $customer->id,
        'cash_account_id' => $account->id,
        'receipt_date' => now()->toDateString(),
        'amount' => 200_000,
    ], [
        ['target_id' => $receivable->id, 'amount' => 200_000],
    ]);

    $receipts->post($receipt);
    $receipts->cancel($receipt->fresh());

    expect($receivable->fresh()->paid_amount)->toBe(0.0);
    expect($receivable->fresh()->status)->toBe(ReceivableStatus::Open);
    expect($account->fresh()->balance)->toBe(0.0);
    expect(CashTransaction::where('cash_account_id', $account->id)->count())->toBe(2);
});

it('melunasi hutang supplier dan mengurangi saldo kas', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $supplier = makeSupplier($company->id, ['payment_term' => 'net_30']);
    $orders = app(PurchaseOrderService::class);
    $receiptsService = app(GoodsReceiptService::class);
    $invoices = app(PurchaseInvoiceService::class);

    $order = $orders->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 50, 'unit_price' => 10_000],
    ]);

    $orders->submit($order);

    $goodsReceipt = $receiptsService->save(null, [
        'purchase_order_id' => $order->id,
        'receipt_date' => now()->toDateString(),
        'warehouse_id' => $warehouse->id,
    ], $receiptsService->draftItemsFromOrder($order->fresh()));

    $receiptsService->post($goodsReceipt);

    $invoice = $invoices->save(null, [
        'supplier_id' => $supplier->id,
        'purchase_order_id' => $order->id,
        'branch_id' => $branch->id,
        'invoice_date' => now()->toDateString(),
    ], $invoices->draftItemsFromReceipt($goodsReceipt->fresh()));

    $invoices->post($invoice);

    $payable = Payable::where('document_id', $invoice->id)->firstOrFail();
    $account = makeCashAccount($company->id, ['type' => 'bank', 'name' => 'BCA Operasional']);

    app(CashService::class)->record($account, 'in', 1_000_000, 'opening', 'Setoran modal kerja');

    $payments = app(PaymentService::class);

    $payment = $payments->save(null, [
        'supplier_id' => $supplier->id,
        'cash_account_id' => $account->id,
        'payment_date' => now()->toDateString(),
        'method' => 'transfer',
        'amount' => 500_000,
    ], [
        ['target_id' => $payable->id, 'amount' => 500_000],
    ]);

    $payments->post($payment);

    expect($payable->fresh()->status)->toBe(PayableStatus::Paid);
    expect($payable->fresh()->outstanding_amount)->toBe(0.0);
    expect($invoice->fresh()->outstanding_amount)->toBe(0.0);
    expect($account->fresh()->balance)->toBe(500_000.0);
    expect($supplier->fresh()->outstanding_amount)->toBe(0.0);
});

it('menolak pembayaran yang melebihi saldo kas', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $supplier = makeSupplier($company->id);
    $orders = app(PurchaseOrderService::class);
    $goodsReceipts = app(GoodsReceiptService::class);
    $invoices = app(PurchaseInvoiceService::class);

    $order = $orders->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 10, 'unit_price' => 10_000],
    ]);

    $orders->submit($order);

    $goodsReceipt = $goodsReceipts->save(null, [
        'purchase_order_id' => $order->id,
        'receipt_date' => now()->toDateString(),
        'warehouse_id' => $warehouse->id,
    ], $goodsReceipts->draftItemsFromOrder($order->fresh()));

    $goodsReceipts->post($goodsReceipt);

    $invoice = $invoices->save(null, [
        'supplier_id' => $supplier->id,
        'branch_id' => $branch->id,
        'invoice_date' => now()->toDateString(),
    ], $invoices->draftItemsFromReceipt($goodsReceipt->fresh()));

    $invoices->post($invoice);

    $payable = Payable::where('document_id', $invoice->id)->firstOrFail();
    $account = makeCashAccount($company->id);
    $payments = app(PaymentService::class);

    $payment = $payments->save(null, [
        'supplier_id' => $supplier->id,
        'cash_account_id' => $account->id,
        'payment_date' => now()->toDateString(),
        'amount' => 100_000,
    ], [
        ['target_id' => $payable->id, 'amount' => 100_000],
    ]);

    expect(fn () => $payments->post($payment))->toThrow(RuntimeException::class);
    expect($account->fresh()->balance)->toBe(0.0);
});

it('mencatat transfer antar kas sebagai dua mutasi', function (): void {
    ['company' => $company] = $this->bootCompany();

    $cash = makeCashAccount($company->id, ['name' => 'Kas Toko']);
    $bank = makeCashAccount($company->id, ['type' => 'bank', 'name' => 'BCA']);

    $service = app(CashService::class);

    $service->record($cash, 'in', 2_000_000, 'opening', 'Saldo awal');
    $service->transfer($cash, $bank, 750_000, 'Setor ke bank');

    expect($cash->fresh()->balance)->toBe(1_250_000.0);
    expect($bank->fresh()->balance)->toBe(750_000.0);
    expect(CashTransaction::where('cash_account_id', $cash->id)->count())->toBe(2);
    expect($service->balanceAt($bank->fresh(), now()))->toBe(750_000.0);
});

it('mengelompokkan piutang ke bucket umur', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();

    ['receivable' => $receivable] = buildReceivable($company->id, $warehouse->id, $branch->id);

    // Geser jatuh tempo ke masa lalu agar masuk bucket 31-60 hari.
    $receivable->update(['due_date' => now()->subDays(45)->toDateString()]);

    $aging = app(AgingService::class);
    $groups = $aging->receivables();

    expect($groups)->toHaveCount(1);
    expect($groups->first()['buckets']['31-60 hari'])->toBe($receivable->fresh()->outstanding_amount);
    expect($aging->totals($groups)['total'])->toBe($receivable->fresh()->outstanding_amount);
    expect($receivable->fresh()->status)->toBe(ReceivableStatus::Open);
});

it('mengalokasikan penerimaan ke tagihan tertua lebih dulu', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();

    ['receivable' => $first, 'customer' => $customer] =
        buildReceivable($company->id, $warehouse->id, $branch->id, quantity: 10);

    $first->update(['due_date' => now()->subDays(10)->toDateString(), 'invoice_date' => now()->subDays(40)->toDateString()]);

    $second = Receivable::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'document_type' => 'sales_invoice',
        'document_id' => 999_001,
        'document_number' => 'INV/TEST/2',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(20)->toDateString(),
        'amount' => 300_000,
        'paid_amount' => 0,
    ]);

    $second->refreshStatus()->save();

    // Uang yang masuk menutup tagihan jatuh tempo lebih dulu, sisanya baru
    // dipakai untuk tagihan yang lebih baru.
    $lines = app(PaymentAllocationService::class)->suggestForCustomer($customer->fresh(), 250_000);

    expect($lines)->toHaveCount(2);
    expect($lines[0]['target_id'])->toBe($first->id);
    expect($lines[0]['amount'])->toBe(200_000.0);
    expect($lines[1]['target_id'])->toBe($second->id);
    expect($lines[1]['amount'])->toBe(50_000.0);

    $partial = app(PaymentAllocationService::class)->suggestForCustomer($customer->fresh(), 150_000);

    expect($partial)->toHaveCount(1);
    expect($partial[0]['target_id'])->toBe($first->id);
});

it('mengharuskan alokasi sebelum penerimaan bisa diposting', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();

    ['customer' => $customer] = buildReceivable($company->id, $warehouse->id, $branch->id);

    $account = makeCashAccount($company->id);
    $receipts = app(ReceiptService::class);

    $receipt = $receipts->save(null, [
        'customer_id' => $customer->id,
        'cash_account_id' => $account->id,
        'receipt_date' => now()->toDateString(),
        'amount' => 100_000,
    ], []);

    expect($receipt->status)->toBe(DocumentStatus::Draft);
    expect(fn () => $receipts->post($receipt))->toThrow(RuntimeException::class);
});
