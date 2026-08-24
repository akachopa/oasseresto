<?php

declare(strict_types=1);

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\FinancialReportService;
use App\Modules\Company\Models\Company;
use App\Modules\Core\Enums\JournalStatus;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Customer\Models\Customer;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Supplier\Models\Supplier;
use Database\Seeders\DatabaseSeeder;

it('menghasilkan katalog dan transaksi demo dengan trial balance seimbang', function (): void {
    $this->seed(DatabaseSeeder::class);

    $company = Company::withoutGlobalScopes()->where('code', 'SGB')->firstOrFail();
    app(ScopeManager::class)->setCompanyId($company->id);

    expect(Product::count())->toBeGreaterThanOrEqual(200);
    expect(Customer::count())->toBeGreaterThanOrEqual(50);
    expect(Supplier::count())->toBeGreaterThanOrEqual(10);
    expect(PurchaseOrder::count())->toBeGreaterThanOrEqual(2);
    expect(SalesInvoice::count())->toBeGreaterThanOrEqual(2);

    $journals = JournalEntry::query()->get();
    expect($journals)->not->toBeEmpty();
    $journals->each(function (JournalEntry $entry): void {
        expect($entry->isBalanced())->toBeTrue();
        expect($entry->status)->not->toBe(JournalStatus::Draft);
    });

    $tb = app(FinancialReportService::class)->trialBalance(
        $company,
        now()->startOfYear(),
        now()->endOfMonth(),
    );

    expect($tb['balanced'])->toBeTrue();
    expect($tb['total_debit'])->toBeGreaterThan(0);
    expect($tb['total_debit'])->toEqual($tb['total_credit']);
});
