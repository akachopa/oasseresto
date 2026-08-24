<?php

declare(strict_types=1);

use App\Modules\Customer\Models\Customer;
use App\Modules\Delivery\Services\DeliveryService;
use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Services\SalesInvoiceService;
use App\Modules\Sales\Services\SalesOrderService;

function makeCashAccount(int $companyId, array $attributes = []): CashAccount
{
    return CashAccount::create(array_merge([
        'company_id' => $companyId,
        'code' => 'KAS-'.str()->random(4),
        'name' => 'Kas Besar',
        'type' => 'cash',
        'opening_balance' => 0,
        'balance' => 0,
        'is_active' => true,
    ], $attributes));
}

function makeExpenseCategory(int $companyId, array $attributes = []): ExpenseCategory
{
    return ExpenseCategory::create(array_merge([
        'company_id' => $companyId,
        'code' => 'EXP-'.str()->random(4),
        'name' => 'Biaya Operasional',
        'requires_approval' => true,
        'approval_threshold' => 1_000_000,
        'is_active' => true,
    ], $attributes));
}

/**
 * Bangun satu piutang riil lewat alur penjualan, supaya pengujian pelunasan
 * memakai data yang benar-benar terbentuk dari dokumen bisnis.
 *
 * @return array{receivable: Receivable, invoice: SalesInvoice, customer: Customer}
 */
function buildReceivable(
    int $companyId,
    int $warehouseId,
    int $branchId,
    float $unitPrice = 20_000,
    float $quantity = 20,
): array {
    ['product' => $product, 'pcs' => $pcs] = stockProduct($companyId);

    receiveStock($product, $warehouseId, $quantity * 2, 10_000);

    $customer = makeCustomer($companyId, ['payment_term' => 'net_30']);
    $orders = app(SalesOrderService::class);
    $deliveries = app(DeliveryService::class);
    $invoices = app(SalesInvoiceService::class);

    $order = $orders->save(null, [
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouseId,
        'order_date' => now()->toDateString(),
    ], [
        ['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => $quantity, 'unit_price' => $unitPrice],
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
        'branch_id' => $branchId,
        'invoice_date' => now()->toDateString(),
    ], $invoices->draftItemsFromDelivery($delivery->fresh()));

    $invoices->post($invoice);

    return [
        'receivable' => Receivable::where('document_id', $invoice->id)->firstOrFail(),
        'invoice' => $invoice->fresh(),
        'customer' => $customer->fresh(),
    ];
}
