<?php

declare(strict_types=1);

use App\Modules\Accounting\Exceptions\ClosedPeriodException;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountingPeriodService;
use App\Modules\Accounting\Services\AccountingPostingService;
use App\Modules\Accounting\Services\DocumentPostingMapper;
use App\Modules\Accounting\Services\FinancialReportService;
use App\Modules\Core\Enums\JournalStatus;
use App\Modules\Finance\Services\CashService;
use App\Modules\Finance\Services\ReceiptService;
use App\Modules\Purchase\Services\GoodsReceiptService;
use App\Modules\Purchase\Services\PurchaseInvoiceService;
use App\Modules\Purchase\Services\PurchaseOrderService;

it('mencatat jurnal seimbang saat penerimaan dan faktur pembelian diposting', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $supplier = makeSupplier($company->id);
    $orders = app(PurchaseOrderService::class);
    $receipts = app(GoodsReceiptService::class);
    $invoices = app(PurchaseInvoiceService::class);

    $order = $orders->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 10, 'unit_price' => 10_000],
    ]);
    $orders->submit($order);

    $receipt = $receipts->save(null, [
        'purchase_order_id' => $order->id,
        'receipt_date' => now()->toDateString(),
        'warehouse_id' => $warehouse->id,
    ], $receipts->draftItemsFromOrder($order->fresh()));
    $receipts->post($receipt);

    $invoice = $invoices->save(null, [
        'supplier_id' => $supplier->id,
        'purchase_order_id' => $order->id,
        'invoice_date' => now()->toDateString(),
    ], $invoices->draftItemsFromReceipt($receipt->fresh()));
    $invoices->post($invoice);

    $journals = JournalEntry::query()->orderBy('id')->get();

    expect($journals)->not->toBeEmpty();
    $journals->each(function (JournalEntry $entry): void {
        expect($entry->isBalanced())->toBeTrue();
        expect($entry->status)->toBe(JournalStatus::Posted);
        expect($entry->lines)->not->toBeEmpty();
    });

    expect(JournalEntry::where('document_type', 'goods_receipt')->where('document_id', $receipt->id)->count())->toBe(1);
    expect(JournalEntry::where('document_type', 'purchase_invoice')->where('document_id', $invoice->id)->count())->toBe(1);
});

it('tidak menduplikasi jurnal bila dokumen diposting ulang', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $supplier = makeSupplier($company->id);
    $orders = app(PurchaseOrderService::class);
    $receipts = app(GoodsReceiptService::class);

    $order = $orders->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 5, 'unit_price' => 8_000],
    ]);
    $orders->submit($order);

    $receipt = $receipts->save(null, [
        'purchase_order_id' => $order->id,
        'receipt_date' => now()->toDateString(),
        'warehouse_id' => $warehouse->id,
    ], $receipts->draftItemsFromOrder($order->fresh()));
    $receipts->post($receipt);

    $mapper = app(DocumentPostingMapper::class);
    $posting = app(AccountingPostingService::class);
    $draft = $mapper->goodsReceipt($receipt->fresh());

    $first = $posting->post($draft);
    $second = $posting->post($draft);

    expect($second->is($first))->toBeTrue();
    expect(JournalEntry::where('document_type', 'goods_receipt')->where('document_id', $receipt->id)->count())->toBe(1);
});

it('menolak posting ke periode yang sudah ditutup', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);
    $owner = $this->makeUser($company, 'Owner');

    $period = app(AccountingPeriodService::class)->findOrCreate($company, now());
    app(AccountingPeriodService::class)->close($period, $owner->id);

    $supplier = makeSupplier($company->id);
    $orders = app(PurchaseOrderService::class);
    $receipts = app(GoodsReceiptService::class);

    $order = $orders->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 2, 'unit_price' => 5_000],
    ]);
    $orders->submit($order);

    $receipt = $receipts->save(null, [
        'purchase_order_id' => $order->id,
        'receipt_date' => now()->toDateString(),
        'warehouse_id' => $warehouse->id,
    ], $receipts->draftItemsFromOrder($order->fresh()));

    expect(fn () => $receipts->post($receipt))->toThrow(ClosedPeriodException::class);
    expect($receipt->fresh()->status->value)->toBe('draft');
});

it('menjaga neraca saldo seimbang setelah penjualan dan pelunasan', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();

    ['invoice' => $invoice, 'receivable' => $receivable, 'customer' => $customer] =
        buildReceivable($company->id, $warehouse->id, $branch->id);

    $account = makeCashAccount($company->id);
    app(CashService::class)->record($account, 'in', 1_000_000, 'opening', 'Saldo awal');

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

    JournalEntry::query()->get()->each(fn (JournalEntry $entry) => expect($entry->isBalanced())->toBeTrue());

    $tb = app(FinancialReportService::class)->trialBalance(
        $company,
        now()->startOfMonth(),
        now()->endOfMonth(),
    );

    expect($tb['balanced'])->toBeTrue();
    expect($tb['total_debit'])->toBeGreaterThan(0);
});

it('membalik jurnal lewat reversal tanpa mengubah jurnal asli', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $supplier = makeSupplier($company->id);
    $orders = app(PurchaseOrderService::class);
    $receipts = app(GoodsReceiptService::class);

    $order = $orders->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 3, 'unit_price' => 12_000],
    ]);
    $orders->submit($order);

    $receipt = $receipts->save(null, [
        'purchase_order_id' => $order->id,
        'receipt_date' => now()->toDateString(),
        'warehouse_id' => $warehouse->id,
    ], $receipts->draftItemsFromOrder($order->fresh()));
    $receipts->post($receipt);

    $original = JournalEntry::where('document_type', 'goods_receipt')->where('document_id', $receipt->id)->firstOrFail();
    $reversal = app(AccountingPostingService::class)->reverse($original);

    expect($original->fresh()->status)->toBe(JournalStatus::Reversed);
    expect($reversal->reversal_of_id)->toBe($original->id);
    expect($reversal->isBalanced())->toBeTrue();
    expect($reversal->lines->first()->debit)->toBe($original->lines->first()->credit);
});
