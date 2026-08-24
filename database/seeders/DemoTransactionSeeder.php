<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Customer\Models\Customer;
use App\Modules\Delivery\Services\DeliveryService;
use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\Finance\Models\Payable;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Finance\Services\CashService;
use App\Modules\Finance\Services\ExpenseService;
use App\Modules\Finance\Services\PaymentService;
use App\Modules\Finance\Services\ReceiptService;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Services\GoodsReceiptService;
use App\Modules\Purchase\Services\PurchaseInvoiceService;
use App\Modules\Purchase\Services\PurchaseOrderService;
use App\Modules\Purchase\Services\PurchaseRequestService;
use App\Modules\Sales\Services\SalesInvoiceService;
use App\Modules\Sales\Services\SalesOrderService;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Transaksi contoh lewat layanan domain supaya jurnal, stok, AR/AP
 * terbentuk sama seperti pemakaian nyata.
 */
class DemoTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'SGB')->firstOrFail();

        if (PurchaseOrder::withoutGlobalScopes()->where('company_id', $company->id)->exists()) {
            return;
        }

        $owner = User::withoutGlobalScopes()->where('email', 'owner@oasse.id')->firstOrFail();
        $branch = Branch::where('company_id', $company->id)->where('code', 'BKL')->firstOrFail();
        $warehouse = Warehouse::where('company_id', $company->id)->where('code', 'GU-BKL')->firstOrFail();

        app(ScopeManager::class)->forCompany($company->id, function () use ($owner, $warehouse, $branch): void {
            Auth::login($owner);
            setPermissionsTeamId($owner->company_id);

            $this->openCash();
            $this->purchaseFromRequest($warehouse);
            $this->directPurchase($warehouse);
            $this->pendingLargeOrder($warehouse);
            $this->salesCycle($warehouse, $branch, 'CUS-01', ['BRS-01', 'MG-01', 'GLA-01'], now()->toDateString());
            $this->salesCycle($warehouse, $branch, 'CUS-02', ['MIE-01', 'KPI-01', 'SNK-01'], now()->toDateString());
            $this->salesCycle($warehouse, $branch, 'CUS-08', ['AIR-01', 'SBN-01', 'DTR-01'], now()->subDays(50)->toDateString());
            $this->collectAndPay();
            $this->postExpense($branch);

            Auth::logout();
        }, $branch->id);
    }

    private function openCash(): void
    {
        $account = CashAccount::where('code', 'KAS-01')->firstOrFail();

        if ($account->balance > 0) {
            return;
        }

        app(CashService::class)->record(
            $account,
            'in',
            75_000_000,
            'opening',
            'Saldo awal kas operasional',
        );
    }

    private function purchaseFromRequest(Warehouse $warehouse): void
    {
        $supplier = Supplier::where('code', 'SUP-06')->firstOrFail();
        $items = $this->purchaseItems(['BRS-01', 'MG-01', 'GLA-01', 'TPG-01'], 40);

        $request = app(PurchaseRequestService::class)->save(null, [
            'warehouse_id' => $warehouse->id,
            'request_date' => now()->subDays(10)->toDateString(),
            'priority' => 'high',
        ], array_map(fn (array $item) => [
            'product_id' => $item['product_id'],
            'unit_id' => $item['unit_id'],
            'quantity' => $item['quantity'],
            'estimated_price' => $item['unit_price'],
        ], $items));

        app(PurchaseRequestService::class)->submit($request);

        $order = app(PurchaseOrderService::class)->fromRequest($request->fresh(), $supplier->id, [
            'order_date' => now()->subDays(8)->toDateString(),
        ]);
        app(PurchaseOrderService::class)->submit($order);
        $this->receiveAndInvoice($order->fresh(), $warehouse, now()->subDays(6)->toDateString());
    }

    private function directPurchase(Warehouse $warehouse): void
    {
        $supplier = Supplier::where('code', 'SUP-01')->firstOrFail();
        $items = $this->purchaseItems(['MIE-01', 'KPI-01', 'SNK-01', 'AIR-01', 'SBN-01', 'DTR-01'], 60);

        $order = app(PurchaseOrderService::class)->save(null, [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => now()->subDays(5)->toDateString(),
        ], $items);

        app(PurchaseOrderService::class)->submit($order);
        $this->receiveAndInvoice($order->fresh(), $warehouse, now()->subDays(3)->toDateString());
    }

    private function pendingLargeOrder(Warehouse $warehouse): void
    {
        $supplier = Supplier::where('code', 'SUP-07')->firstOrFail();
        $product = Product::where('sku', 'BRS-02')->firstOrFail();

        $order = app(PurchaseOrderService::class)->save(null, [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => now()->toDateString(),
        ], [[
            'product_id' => $product->id,
            'unit_id' => $product->base_unit_id,
            'quantity' => 900,
            'unit_price' => 14_000,
        ]]);

        app(PurchaseOrderService::class)->submit($order);
    }

    /**
     * @param  array<int, string>  $skus
     */
    private function salesCycle(Warehouse $warehouse, Branch $branch, string $customerCode, array $skus, string $date): void
    {
        $customer = Customer::where('code', $customerCode)->firstOrFail();
        $lines = [];

        foreach ($skus as $sku) {
            $product = Product::where('sku', $sku)->firstOrFail();
            $lines[] = [
                'product_id' => $product->id,
                'unit_id' => $product->base_unit_id,
                'quantity' => 8,
                'unit_price' => $product->base_price,
            ];
        }

        $orders = app(SalesOrderService::class);
        $deliveries = app(DeliveryService::class);
        $invoices = app(SalesInvoiceService::class);

        $order = $orders->save(null, [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => $date,
        ], $lines);
        $orders->submit($order);

        $delivery = $deliveries->save(null, [
            'sales_order_id' => $order->id,
            'delivery_date' => $date,
        ], $deliveries->draftItemsFromOrder($order->fresh()));

        $deliveries->pick($delivery, []);
        $deliveries->dispatch($delivery->fresh());

        $invoice = $invoices->save(null, [
            'customer_id' => $customer->id,
            'sales_order_id' => $order->id,
            'delivery_id' => $delivery->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'invoice_date' => $date,
            'due_date' => Carbon::parse($date)->addDays(14)->toDateString(),
        ], $invoices->draftItemsFromDelivery($delivery->fresh()));

        $invoices->post($invoice);
    }

    private function collectAndPay(): void
    {
        $cash = CashAccount::where('code', 'KAS-01')->firstOrFail();
        $receivable = Receivable::query()->orderBy('id')->first();

        if ($receivable !== null && $receivable->outstanding_amount > 0) {
            $amount = round(min(500_000, $receivable->outstanding_amount), 4);
            $receipts = app(ReceiptService::class);
            $receipt = $receipts->save(null, [
                'customer_id' => $receivable->customer_id,
                'cash_account_id' => $cash->id,
                'receipt_date' => now()->toDateString(),
                'method' => 'transfer',
                'amount' => $amount,
                'reference' => 'BCA-SEED-01',
            ], [
                ['target_id' => $receivable->id, 'amount' => $amount],
            ]);
            $receipts->post($receipt);
        }

        $payable = Payable::query()->orderBy('id')->first();

        if ($payable !== null && $payable->outstanding_amount > 0) {
            $amount = round(min(1_500_000, $payable->outstanding_amount), 4);
            $payments = app(PaymentService::class);
            $payment = $payments->save(null, [
                'supplier_id' => $payable->supplier_id,
                'cash_account_id' => $cash->id,
                'payment_date' => now()->toDateString(),
                'method' => 'transfer',
                'amount' => $amount,
                'reference' => 'TRF-SEED-01',
            ], [
                ['target_id' => $payable->id, 'amount' => $amount],
            ]);
            $payments->post($payment);
        }

        Receivable::query()->get()->each(fn (Receivable $row) => $row->refreshStatus()->save());
        Payable::query()->get()->each(fn (Payable $row) => $row->refreshStatus()->save());
    }

    private function postExpense(Branch $branch): void
    {
        $category = ExpenseCategory::where('code', 'EXP-OPS')->firstOrFail();
        $cash = CashAccount::where('code', 'KAS-01')->firstOrFail();
        $expenses = app(ExpenseService::class);

        $expense = $expenses->save(null, [
            'expense_category_id' => $category->id,
            'cash_account_id' => $cash->id,
            'branch_id' => $branch->id,
            'expense_date' => now()->toDateString(),
            'payee' => 'Toko ATK Bengkulu',
            'amount' => 750_000,
            'description' => 'Alat tulis dan kertas operasional',
        ]);

        $expenses->submit($expense);
        $expenses->post($expense->fresh());
    }

    private function receiveAndInvoice(PurchaseOrder $order, Warehouse $warehouse, string $date): void
    {
        if ($order->status !== DocumentStatus::Approved) {
            return;
        }

        $receipts = app(GoodsReceiptService::class);
        $invoices = app(PurchaseInvoiceService::class);

        $receipt = $receipts->save(null, [
            'purchase_order_id' => $order->id,
            'receipt_date' => $date,
            'warehouse_id' => $warehouse->id,
        ], $receipts->draftItemsFromOrder($order->fresh()));
        $receipts->post($receipt);

        $invoice = $invoices->save(null, [
            'supplier_id' => $order->supplier_id,
            'purchase_order_id' => $order->id,
            'branch_id' => $warehouse->branch_id,
            'invoice_date' => $date,
        ], $invoices->draftItemsFromReceipt($receipt->fresh()));
        $invoices->post($invoice);
    }

    /**
     * @param  array<int, string>  $skus
     * @return array<int, array{product_id: int, unit_id: int, quantity: float, unit_price: float}>
     */
    private function purchaseItems(array $skus, float $quantity): array
    {
        $items = [];

        foreach ($skus as $sku) {
            $product = Product::where('sku', $sku)->firstOrFail();
            $items[] = [
                'product_id' => $product->id,
                'unit_id' => $product->base_unit_id,
                'quantity' => $quantity,
                'unit_price' => (float) $product->last_purchase_cost,
            ];
        }

        return $items;
    }
}
