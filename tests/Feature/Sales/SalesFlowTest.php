<?php

declare(strict_types=1);

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Core\Enums\DeliveryStatus;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Enums\ReceivableStatus;
use App\Modules\Core\Models\TaxCode;
use App\Modules\Delivery\Services\DeliveryService;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Services\CreditControlService;
use App\Modules\Sales\Services\QuotationService;
use App\Modules\Sales\Services\SalesInvoiceService;
use App\Modules\Sales\Services\SalesOrderService;
use App\Modules\Sales\Services\SalesReturnService;

it('menjadikan penawaran sebagai sales order dengan harga yang dijanjikan', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $customer = makeCustomer($company->id);
    $quotations = app(QuotationService::class);

    $quotation = $quotations->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'quotation_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 10, 'unit_price' => 18_000],
    ]);

    expect($quotation->total)->toBe(180_000.0);

    $quotations->send($quotation);

    $order = app(SalesOrderService::class)->fromQuotation($quotation->fresh());

    expect($order->items)->toHaveCount(1);
    expect($order->items->first()->unit_price)->toBe(18_000.0);
    expect($order->total)->toBe(180_000.0);
    expect($quotation->fresh()->status)->toBe(DocumentStatus::Completed);
});

it('mereservasi stok saat sales order disetujui dan melepasnya saat ditutup', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 100, 10_000);

    $customer = makeCustomer($company->id);
    $orders = app(SalesOrderService::class);
    $stock = app(StockService::class);

    $order = $orders->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 30, 'unit_price' => 20_000],
    ]);

    $orders->submit($order);

    expect($order->fresh()->status)->toBe(DocumentStatus::Approved);
    expect($stock->onHand($product->id, $warehouse->id))->toBe(100.0);
    expect($stock->available($product->id, $warehouse->id))->toBe(70.0);

    $orders->close($order->fresh());

    expect($stock->available($product->id, $warehouse->id))->toBe(100.0);
});

it('memblokir order kredit yang melewati limit saat mode block', function (): void {
    config()->set('oasse.credit.mode', 'block');

    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $customer = makeCustomer($company->id, ['credit_limit' => 100_000]);
    $orders = app(SalesOrderService::class);

    $order = $orders->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 50, 'unit_price' => 20_000],
    ]);

    expect(fn () => $orders->submit($order))->toThrow(RuntimeException::class);

    $order->refresh();

    expect($order->status)->toBe(DocumentStatus::Draft);
    expect($order->credit_status)->toBe('blocked');
});

it('meminta approval saat order kredit melewati limit pada mode approval', function (): void {
    config()->set('oasse.credit.mode', 'approval');

    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 100, 10_000);

    $manager = $this->makeUser($company, 'Branch Manager', $branch);

    ApprovalRule::create([
        'company_id' => $company->id,
        'document_type' => 'sales_order',
        'name' => 'Kredit di atas limit',
        'sequence' => 1,
        'trigger' => 'credit_limit',
        'approver_role' => 'Branch Manager',
    ]);

    $customer = makeCustomer($company->id, ['credit_limit' => 100_000]);
    $orders = app(SalesOrderService::class);

    $order = $orders->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 50, 'unit_price' => 20_000],
    ]);

    $orders->submit($order);

    $order->refresh();

    expect($order->status)->toBe(DocumentStatus::Submitted);
    expect($order->credit_status)->toBe('approval');

    $approval = ApprovalRequest::where('document_type', 'sales_order')
        ->where('document_id', $order->id)
        ->firstOrFail();

    app(ApprovalService::class)->approve($approval, $manager);

    $order->refresh()->load('items');

    expect($order->status)->toBe(DocumentStatus::Approved);
    expect($order->items->first()->reserved_base_quantity)->toBe(50.0);
});

it('menjual tunai tanpa memeriksa credit limit', function (): void {
    config()->set('oasse.credit.mode', 'block');

    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    $customer = makeCustomer($company->id, ['credit_limit' => 0, 'payment_term' => 'cash']);
    $orders = app(SalesOrderService::class);

    $order = $orders->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 10, 'unit_price' => 20_000],
    ]);

    $orders->submit($order);

    expect($order->fresh()->status)->toBe(DocumentStatus::Approved);
    expect($order->fresh()->credit_status)->toBe('ok');
});

it('mengurangi stok saat surat jalan dikirim dan menyisakan kekurangan di order', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id, trackBatch: true);

    receiveStock($product, $warehouse->id, 40, 10_000, batchNumber: 'LOT-A', expiry: now()->addMonths(2)->toDateString());
    receiveStock($product, $warehouse->id, 40, 12_000, batchNumber: 'LOT-B', expiry: now()->addMonths(6)->toDateString());

    $customer = makeCustomer($company->id);
    $orders = app(SalesOrderService::class);
    $deliveries = app(DeliveryService::class);

    $order = $orders->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 50, 'unit_price' => 20_000],
    ]);

    $orders->submit($order);

    $delivery = $deliveries->save(null, [
        'sales_order_id' => $order->id,
        'delivery_date' => now()->toDateString(),
        'driver_name' => 'Budi',
    ], $deliveries->draftItemsFromOrder($order->fresh()));

    // Gudang hanya berhasil menyiapkan 30 dari 50 yang dipesan.
    $deliveries->pick($delivery, [$delivery->items->first()->id => 30]);
    $deliveries->dispatch($delivery->fresh());

    $delivery->refresh()->load('items');
    $order->refresh()->load('items');

    expect($delivery->status)->toBe(DeliveryStatus::Dispatched);
    // Batch fisik diambil FEFO (LOT-A), sedangkan nilainya memakai rata-rata
    // bergerak sesuai metode costing default company: 30 x 11.000.
    expect($delivery->items->first()->batch?->batch_number)->toBe('LOT-A');
    expect($delivery->total_value)->toBe(330_000.0);
    expect($order->status)->toBe(DocumentStatus::PartiallyProcessed);
    expect($order->items->first()->delivered_base_quantity)->toBe(30.0);
    expect($order->outstandingBaseQuantity())->toBe(20.0);
    expect(app(StockService::class)->onHand($product->id, $warehouse->id))->toBe(50.0);

    $deliveries->complete($delivery->fresh(), 'Ibu Sari');

    expect($delivery->fresh()->status)->toBe(DeliveryStatus::Delivered);
});

it('menolak pengiriman yang melebihi sisa sales order', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 100, 10_000);

    $customer = makeCustomer($company->id);
    $orders = app(SalesOrderService::class);
    $deliveries = app(DeliveryService::class);

    $order = $orders->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 10, 'unit_price' => 20_000],
    ]);

    $orders->submit($order);

    expect(fn () => $deliveries->save(null, [
        'sales_order_id' => $order->id,
        'delivery_date' => now()->toDateString(),
    ], [
        [
            'product_id' => $product->id,
            'unit_id' => $pcs->id,
            'sales_order_item_id' => $order->items->first()->id,
            'quantity' => 15,
        ],
    ]))->toThrow(RuntimeException::class);
});

it('mengembalikan stok saat pengiriman gagal', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 50, 10_000);

    $customer = makeCustomer($company->id);
    $orders = app(SalesOrderService::class);
    $deliveries = app(DeliveryService::class);
    $stock = app(StockService::class);

    $order = $orders->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 20, 'unit_price' => 20_000],
    ]);

    $orders->submit($order);

    $delivery = $deliveries->save(null, [
        'sales_order_id' => $order->id,
        'delivery_date' => now()->toDateString(),
    ], $deliveries->draftItemsFromOrder($order->fresh()));

    $deliveries->pick($delivery, []);
    $deliveries->dispatch($delivery->fresh());

    expect($stock->onHand($product->id, $warehouse->id))->toBe(30.0);

    $deliveries->fail($delivery->fresh(), 'Alamat tidak ditemukan.');

    $order->refresh()->load('items');

    expect($stock->onHand($product->id, $warehouse->id))->toBe(50.0);
    expect($order->items->first()->delivered_base_quantity)->toBe(0.0);
});

it('membentuk piutang saat invoice penjualan diposting', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 100, 10_000);

    $customer = makeCustomer($company->id, ['payment_term' => 'net_30']);
    $tax = TaxCode::where('code', 'PPN11')->firstOrFail();

    $orders = app(SalesOrderService::class);
    $deliveries = app(DeliveryService::class);
    $invoices = app(SalesInvoiceService::class);

    $order = $orders->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        [
            'product_id' => $product->id,
            'unit_id' => $pcs->id,
            'quantity' => 20,
            'unit_price' => 20_000,
            'tax_code_id' => $tax->id,
        ],
    ]);

    $orders->submit($order);

    $delivery = $deliveries->save(null, [
        'sales_order_id' => $order->id,
        'delivery_date' => now()->toDateString(),
    ], $deliveries->draftItemsFromOrder($order->fresh()));

    $deliveries->pick($delivery, []);
    $deliveries->dispatch($delivery->fresh());

    $invoice = $invoices->save(null, [
        'customer_id' => $customer->id,
        'sales_order_id' => $order->id,
        'delivery_id' => $delivery->id,
        'branch_id' => $warehouse->branch_id,
        'warehouse_id' => $warehouse->id,
        'invoice_date' => now()->toDateString(),
    ], $invoices->draftItemsFromDelivery($delivery->fresh()));

    expect($invoice->subtotal)->toBe(400_000.0);
    expect($invoice->tax_amount)->toBe(44_000.0);
    expect($invoice->total)->toBe(444_000.0);
    expect($invoice->cost_of_goods)->toBe(200_000.0);

    $invoices->post($invoice);

    $receivable = Receivable::where('document_id', $invoice->id)->firstOrFail();

    expect($receivable->amount)->toBe(444_000.0);
    expect($receivable->outstanding_amount)->toBe(444_000.0);
    expect($receivable->status)->toBe(ReceivableStatus::Open);
    expect($receivable->due_date->toDateString())->toBe(now()->addDays(30)->toDateString());
    expect($customer->fresh()->outstanding_amount)->toBe(444_000.0);
    expect($invoice->fresh()->marginPercent())->toBe(50.0);
});

it('menolak tagihan yang melebihi barang terkirim dan belum ditagih', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 100, 10_000);

    $customer = makeCustomer($company->id);
    $orders = app(SalesOrderService::class);
    $deliveries = app(DeliveryService::class);
    $invoices = app(SalesInvoiceService::class);

    $order = $orders->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 10, 'unit_price' => 20_000],
    ]);

    $orders->submit($order);

    $delivery = $deliveries->save(null, [
        'sales_order_id' => $order->id,
        'delivery_date' => now()->toDateString(),
    ], $deliveries->draftItemsFromOrder($order->fresh()));

    $deliveries->pick($delivery, []);
    $deliveries->dispatch($delivery->fresh());

    $deliveryItem = $delivery->fresh()->items->first();

    $invoice = $invoices->save(null, [
        'customer_id' => $customer->id,
        'branch_id' => $warehouse->branch_id,
        'invoice_date' => now()->toDateString(),
    ], [
        [
            'product_id' => $product->id,
            'unit_id' => $pcs->id,
            'delivery_item_id' => $deliveryItem->id,
            'quantity' => 15,
            'unit_price' => 20_000,
        ],
    ]);

    expect(fn () => $invoices->post($invoice))->toThrow(RuntimeException::class);
});

it('mengembalikan stok dan mengurangi piutang saat retur penjualan diposting', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 100, 10_000);

    $customer = makeCustomer($company->id);
    $orders = app(SalesOrderService::class);
    $deliveries = app(DeliveryService::class);
    $invoices = app(SalesInvoiceService::class);
    $returns = app(SalesReturnService::class);
    $stock = app(StockService::class);

    $order = $orders->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 20, 'unit_price' => 20_000],
    ]);

    $orders->submit($order);

    $delivery = $deliveries->save(null, [
        'sales_order_id' => $order->id,
        'delivery_date' => now()->toDateString(),
    ], $deliveries->draftItemsFromOrder($order->fresh()));

    $deliveries->pick($delivery, []);
    $deliveries->dispatch($delivery->fresh());

    $invoice = $invoices->save(null, [
        'customer_id' => $customer->id,
        'sales_order_id' => $order->id,
        'delivery_id' => $delivery->id,
        'branch_id' => $warehouse->branch_id,
        'invoice_date' => now()->toDateString(),
    ], $invoices->draftItemsFromDelivery($delivery->fresh()));

    $invoices->post($invoice);

    expect($stock->onHand($product->id, $warehouse->id))->toBe(80.0);

    $return = $returns->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'sales_invoice_id' => $invoice->id,
        'return_date' => now()->toDateString(),
        'reason' => 'not_as_ordered',
        'settlement' => 'credit_note',
        'restock' => true,
    ], [
        [
            'product_id' => $product->id,
            'unit_id' => $pcs->id,
            'sales_invoice_item_id' => $invoice->fresh()->items->first()->id,
            'quantity' => 5,
            'unit_price' => 20_000,
            'unit_cost' => 10_000,
        ],
    ]);

    $returns->post($return);

    $receivable = Receivable::where('document_id', $invoice->id)->firstOrFail();

    expect($stock->onHand($product->id, $warehouse->id))->toBe(85.0);
    expect($receivable->amount)->toBe(300_000.0);
    expect($invoice->fresh()->outstanding_amount)->toBe(300_000.0);
});

it('tidak memasukkan barang rusak kembali ke stok saat retur', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 50, 10_000);

    $customer = makeCustomer($company->id);
    $returns = app(SalesReturnService::class);
    $stock = app(StockService::class);

    $return = $returns->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'return_date' => now()->toDateString(),
        'reason' => 'damaged',
        'settlement' => 'refund',
        'restock' => false,
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 5, 'unit_price' => 20_000, 'unit_cost' => 10_000],
    ]);

    $returns->post($return);

    expect($stock->onHand($product->id, $warehouse->id))->toBe(50.0);
    expect($return->fresh()->cost_of_goods)->toBe(50_000.0);
});

it('menghitung eksposur kredit dari piutang dan order berjalan', function (): void {
    ['company' => $company, 'warehouse' => $warehouse] = $this->bootCompany();
    ['product' => $product, 'pcs' => $pcs] = stockProduct($company->id);

    receiveStock($product, $warehouse->id, 200, 10_000);

    $customer = makeCustomer($company->id, ['credit_limit' => 1_000_000]);
    $orders = app(SalesOrderService::class);
    $credit = app(CreditControlService::class);

    $order = $orders->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 30, 'unit_price' => 20_000],
    ]);

    $orders->submit($order);

    $decision = $credit->evaluate($customer->fresh(), 100_000);

    expect($decision->outstanding)->toBe(600_000.0);
    expect($decision->exposure)->toBe(700_000.0);
    expect($decision->available)->toBe(400_000.0);
    expect($decision->isClear())->toBeTrue();
});
