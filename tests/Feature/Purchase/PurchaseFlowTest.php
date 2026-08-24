<?php

declare(strict_types=1);

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Core\Enums\ApprovalStatus;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\PayableStatus;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Finance\Models\Payable;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Purchase\Services\GoodsReceiptService;
use App\Modules\Purchase\Services\PurchaseInvoiceService;
use App\Modules\Purchase\Services\PurchaseOrderService;
use App\Modules\Purchase\Services\PurchaseRequestService;
use App\Modules\Purchase\Services\PurchaseReturnService;
use App\Modules\Purchase\Services\ReorderService;

it('menyetujui purchase request otomatis bila tidak ada aturan approval', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $requests = app(PurchaseRequestService::class);

    $request = $requests->save(null, [
        'warehouse_id' => $warehouse->id,
        'request_date' => now()->toDateString(),
        'priority' => 'high',
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 100, 'estimated_price' => 10_000],
    ]);

    expect($request->estimated_total)->toBe(1_000_000.0);

    $requests->submit($request);

    expect($request->fresh()->status)->toBe(DocumentStatus::Approved);
});

it('menjalankan approval berjenjang purchase order sesuai nilai dokumen', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $manager = $this->makeUser($company, 'Branch Manager', $branch);
    $gm = $this->makeUser($company, 'General Manager', $branch);

    ApprovalRule::create([
        'company_id' => $company->id,
        'document_type' => 'purchase_order',
        'name' => 'Level 1',
        'sequence' => 1,
        'min_amount' => 1_000_000,
        'approver_role' => 'Branch Manager',
    ]);

    ApprovalRule::create([
        'company_id' => $company->id,
        'document_type' => 'purchase_order',
        'name' => 'Level 2',
        'sequence' => 2,
        'min_amount' => 5_000_000,
        'approver_role' => 'General Manager',
    ]);

    $supplier = makeSupplier($company->id);
    $orders = app(PurchaseOrderService::class);

    $order = $orders->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 1_000, 'unit_price' => 10_000],
    ]);

    expect($order->total)->toBe(10_000_000.0);

    $orders->submit($order);

    $approval = ApprovalRequest::where('document_id', $order->id)->firstOrFail();

    expect($approval->steps)->toHaveCount(2);
    expect($order->fresh()->status)->toBe(DocumentStatus::Submitted);

    $approvals = app(ApprovalService::class);

    $approvals->approve($approval->fresh(), $manager);

    expect($order->fresh()->status)->toBe(DocumentStatus::Submitted);
    expect($approval->fresh()->current_step)->toBe(2);

    $approvals->approve($approval->fresh(), $gm);

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Approved);
    expect($order->fresh()->status)->toBe(DocumentStatus::Approved);
});

it('menolak purchase order lewat approval dan mencatat alasannya', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $manager = $this->makeUser($company, 'Branch Manager', $branch);

    ApprovalRule::create([
        'company_id' => $company->id,
        'document_type' => 'purchase_order',
        'name' => 'Semua PO',
        'sequence' => 1,
        'approver_role' => 'Branch Manager',
    ]);

    $supplier = makeSupplier($company->id);
    $orders = app(PurchaseOrderService::class);

    $order = $orders->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 10, 'unit_price' => 10_000],
    ]);

    $orders->submit($order);

    $approval = ApprovalRequest::where('document_id', $order->id)->firstOrFail();

    app(ApprovalService::class)->reject($approval, $manager, 'Harga terlalu tinggi.');

    $order->refresh();

    expect($order->status)->toBe(DocumentStatus::Rejected);
    expect($order->rejection_reason)->toBe('Harga terlalu tinggi.');
});

it('menolak aksi approval dari user yang bukan penyetujunya', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $salesman = $this->makeUser($company, 'Salesman', $branch);

    ApprovalRule::create([
        'company_id' => $company->id,
        'document_type' => 'purchase_order',
        'name' => 'Semua PO',
        'sequence' => 1,
        'approver_role' => 'Branch Manager',
    ]);

    $supplier = makeSupplier($company->id);
    $orders = app(PurchaseOrderService::class);

    $order = $orders->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 10, 'unit_price' => 10_000],
    ]);

    $orders->submit($order);

    $approval = ApprovalRequest::where('document_id', $order->id)->firstOrFail();

    expect(fn () => app(ApprovalService::class)->approve($approval, $salesman))
        ->toThrow(RuntimeException::class);
});

it('membuat purchase order dari sisa purchase request yang disetujui', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'dus' => $dus] = stockProduct($company->id);

    $supplier = makeSupplier($company->id);
    $requests = app(PurchaseRequestService::class);
    $orders = app(PurchaseOrderService::class);

    $request = $requests->save(null, [
        'warehouse_id' => $warehouse->id,
        'request_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $dus->id, 'quantity' => 10, 'estimated_price' => 100_000],
    ]);

    $requests->submit($request);

    $order = $orders->fromRequest($request->fresh(), $supplier->id);

    expect($order->items)->toHaveCount(1);
    expect($order->items->first()->quantity)->toBe(10.0);
    expect($order->items->first()->base_quantity)->toBe(120.0);

    $orders->submit($order);

    $request->refresh()->load('items');

    expect($request->items->first()->ordered_base_quantity)->toBe(120.0);
    expect($request->status)->toBe(DocumentStatus::Completed);

    expect(fn () => $orders->fromRequest($request->fresh(), $supplier->id))
        ->toThrow(RuntimeException::class);
});

it('membentuk batch dan menulis kartu stok saat penerimaan diposting', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'dus' => $dus] = stockProduct($company->id, trackBatch: true);

    $supplier = makeSupplier($company->id);
    $orders = app(PurchaseOrderService::class);
    $receipts = app(GoodsReceiptService::class);

    $order = $orders->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $dus->id, 'quantity' => 10, 'unit_price' => 120_000],
    ]);

    $orders->submit($order);

    $receipt = $receipts->save(null, [
        'purchase_order_id' => $order->id,
        'receipt_date' => now()->toDateString(),
        'supplier_do_number' => 'DO-001',
        'warehouse_id' => $warehouse->id,
    ], [
        [
            'product_id' => $product->id,
            'unit_id' => $dus->id,
            'purchase_order_item_id' => $order->items->first()->id,
            'quantity' => 4,
            'batch_number' => 'LOT-2026-01',
            'expiry_date' => now()->addYear()->toDateString(),
        ],
    ]);

    $receipts->post($receipt);

    $stock = app(StockService::class);

    // 4 dus = 48 pcs, harga pokok 120.000 / 12 = 10.000 per pcs.
    expect($stock->onHand($product->id, $warehouse->id))->toBe(48.0);
    expect($stock->balance($product->id, $warehouse->id)->average_cost)->toBe(10_000.0);

    $receipt->refresh();

    expect($receipt->status)->toBe(DocumentStatus::Posted);
    expect($receipt->items->first()->batch_id)->not->toBeNull();
    expect($receipt->items->first()->batch->batch_number)->toBe('LOT-2026-01');

    $order->refresh()->load('items');

    expect($order->status)->toBe(DocumentStatus::PartiallyProcessed);
    expect($order->items->first()->received_base_quantity)->toBe(48.0);
    expect($order->isBackorder())->toBeTrue();
});

it('menutup purchase order setelah seluruh barang diterima', function (): void {
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
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 20, 'unit_price' => 10_000],
    ]);

    $orders->submit($order);

    foreach ([12, 8] as $quantity) {
        $draft = $receipts->save(null, [
            'purchase_order_id' => $order->id,
            'receipt_date' => now()->toDateString(),
            'warehouse_id' => $warehouse->id,
        ], [
            [
                'product_id' => $product->id,
                'unit_id' => $pcs->id,
                'purchase_order_item_id' => $order->items->first()->id,
                'quantity' => $quantity,
            ],
        ]);

        $receipts->post($draft);
    }

    $order->refresh()->load('items');

    expect($order->status)->toBe(DocumentStatus::Completed);
    expect($order->outstandingBaseQuantity())->toBe(0.0);
    expect(app(StockService::class)->onHand($product->id, $warehouse->id))->toBe(20.0);
});

it('menolak penerimaan yang melebihi sisa purchase order', function (): void {
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
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 10, 'unit_price' => 10_000],
    ]);

    $orders->submit($order);

    $receipt = $receipts->save(null, [
        'purchase_order_id' => $order->id,
        'receipt_date' => now()->toDateString(),
        'warehouse_id' => $warehouse->id,
    ], [
        [
            'product_id' => $product->id,
            'unit_id' => $pcs->id,
            'purchase_order_item_id' => $order->items->first()->id,
            'quantity' => 15,
        ],
    ]);

    expect(fn () => $receipts->post($receipt))->toThrow(RuntimeException::class);
    expect(app(StockService::class)->onHand($product->id, $warehouse->id))->toBe(0.0);
});

it('tidak memasukkan barang yang ditolak ke dalam stok', function (): void {
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
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 20, 'unit_price' => 10_000],
    ]);

    $orders->submit($order);

    $receipt = $receipts->save(null, [
        'purchase_order_id' => $order->id,
        'receipt_date' => now()->toDateString(),
        'warehouse_id' => $warehouse->id,
    ], [
        [
            'product_id' => $product->id,
            'unit_id' => $pcs->id,
            'purchase_order_item_id' => $order->items->first()->id,
            'quantity' => 20,
            'rejected_base_quantity' => 5,
        ],
    ]);

    $receipts->post($receipt);

    expect(app(StockService::class)->onHand($product->id, $warehouse->id))->toBe(15.0);
    expect($supplier->fresh()->quality_rate)->toBe(75.0);
});

it('membentuk hutang saat invoice pembelian diposting', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $supplier = makeSupplier($company->id, ['payment_term' => 'net_30']);
    $tax = TaxCode::where('code', 'PPN11')->firstOrFail();

    $orders = app(PurchaseOrderService::class);
    $receipts = app(GoodsReceiptService::class);
    $invoices = app(PurchaseInvoiceService::class);

    $order = $orders->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        [
            'product_id' => $product->id,
            'unit_id' => $pcs->id,
            'quantity' => 100,
            'unit_price' => 10_000,
            'tax_code_id' => $tax->id,
        ],
    ]);

    $orders->submit($order);

    $receipt = $receipts->save(null, array_merge(
        ['purchase_order_id' => $order->id, 'receipt_date' => now()->toDateString(), 'warehouse_id' => $warehouse->id],
    ), $receipts->draftItemsFromOrder($order->fresh()));

    $receipts->post($receipt);

    $invoice = $invoices->save(null, [
        'supplier_id' => $supplier->id,
        'purchase_order_id' => $order->id,
        'branch_id' => $warehouse->branch_id,
        'invoice_date' => now()->toDateString(),
        'supplier_invoice_number' => 'INV-SUP-001',
    ], $invoices->draftItemsFromReceipt($receipt->fresh()));

    expect($invoice->subtotal)->toBe(1_000_000.0);
    expect($invoice->tax_amount)->toBe(110_000.0);
    expect($invoice->total)->toBe(1_110_000.0);

    $invoices->post($invoice);

    $payable = Payable::where('document_id', $invoice->id)->firstOrFail();

    expect($payable->amount)->toBe(1_110_000.0);
    expect($payable->outstanding_amount)->toBe(1_110_000.0);
    expect($payable->status)->toBe(PayableStatus::Open);
    expect($payable->due_date->toDateString())->toBe(now()->addDays(30)->toDateString());

    expect($supplier->fresh()->outstanding_amount)->toBe(1_110_000.0);
});

it('menolak tagihan yang melebihi barang diterima dan belum ditagih', function (): void {
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

    $receiptItem = $receipt->fresh()->items->first();

    $invoice = $invoices->save(null, [
        'supplier_id' => $supplier->id,
        'branch_id' => $warehouse->branch_id,
        'invoice_date' => now()->toDateString(),
    ], [
        [
            'product_id' => $product->id,
            'unit_id' => $pcs->id,
            'goods_receipt_item_id' => $receiptItem->id,
            'quantity' => 15,
            'unit_price' => 10_000,
        ],
    ]);

    expect(fn () => $invoices->post($invoice))->toThrow(RuntimeException::class);
});

it('mengurangi stok dan hutang saat retur pembelian diposting', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $supplier = makeSupplier($company->id);
    $orders = app(PurchaseOrderService::class);
    $receipts = app(GoodsReceiptService::class);
    $invoices = app(PurchaseInvoiceService::class);
    $returns = app(PurchaseReturnService::class);

    $order = $orders->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 50, 'unit_price' => 10_000],
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
        'branch_id' => $warehouse->branch_id,
        'invoice_date' => now()->toDateString(),
        'supplier_invoice_number' => 'INV-SUP-002',
    ], $invoices->draftItemsFromReceipt($receipt->fresh()));

    $invoices->post($invoice);

    $return = $returns->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'purchase_invoice_id' => $invoice->id,
        'return_date' => now()->toDateString(),
        'reason' => 'damaged',
        'settlement' => 'credit_note',
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 5, 'unit_price' => 10_000],
    ]);

    $returns->post($return);

    $payable = Payable::where('document_id', $invoice->id)->firstOrFail();

    expect(app(StockService::class)->onHand($product->id, $warehouse->id))->toBe(45.0);
    expect($payable->amount)->toBe(450_000.0);
    expect($invoice->fresh()->outstanding_amount)->toBe(450_000.0);
});

it('merekomendasikan pembelian dengan memperhitungkan barang dalam pesanan', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $product->update([
        'reorder_point' => 50,
        'minimum_stock' => 20,
        'maximum_stock' => 200,
        'last_purchase_cost' => 10_000,
    ]);

    $supplier = makeSupplier($company->id);

    receiveStock($product, $warehouse->id, 30, 10_000);

    $reorder = app(ReorderService::class);

    $before = $reorder->suggestions($warehouse->id);

    expect($before)->toHaveCount(1);
    expect($before->first()->available)->toBe(30.0);
    expect($before->first()->suggested_quantity)->toBe(170.0);
    expect($before->first()->is_critical)->toBeFalse();

    $orders = app(PurchaseOrderService::class);

    $order = $orders->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 100, 'unit_price' => 10_000],
    ]);

    $orders->submit($order);

    // Setelah PO disetujui, barang dalam pesanan membuat saldo proyeksi 130
    // sehingga produk tidak lagi direkomendasikan.
    expect($reorder->suggestions($warehouse->id))->toHaveCount(0);
});

it('menghitung performa supplier dari dokumen penerimaan', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $supplier = makeSupplier($company->id, ['lead_time_days' => 2]);
    $orders = app(PurchaseOrderService::class);
    $receipts = app(GoodsReceiptService::class);

    $order = $orders->save(null, [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->subDays(5)->toDateString(),
        'expected_date' => now()->subDays(3)->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 10, 'unit_price' => 10_000],
    ]);

    $orders->submit($order);

    // Terlambat dua hari dari tanggal yang dijanjikan.
    $receipt = $receipts->save(null, [
        'purchase_order_id' => $order->id,
        'receipt_date' => now()->subDay()->toDateString(),
        'warehouse_id' => $warehouse->id,
    ], $receipts->draftItemsFromOrder($order->fresh()));

    $receipts->post($receipt);

    $supplier->refresh();

    expect($supplier->on_time_rate)->toBe(0.0);
    expect($supplier->quality_rate)->toBe(100.0);
    expect($supplier->average_lead_time)->toBe(4.0);
    expect($supplier->order_count)->toBe(1);
});
