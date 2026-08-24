<?php

declare(strict_types=1);

use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Services\CashService;
use App\Modules\Finance\Services\ExpenseService;
use App\Modules\Finance\Services\ReceiptService;

it('membuka seluruh halaman modul keuangan', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    $owner = $this->makeUser($company, 'Owner', $branch);

    ['receivable' => $receivable, 'customer' => $customer] =
        buildReceivable($company->id, $warehouse->id, $branch->id);

    $account = makeCashAccount($company->id);
    app(CashService::class)->record($account, 'in', 5_000_000, 'opening', 'Saldo awal');

    $receipt = app(ReceiptService::class)->save(null, [
        'customer_id' => $customer->id,
        'cash_account_id' => $account->id,
        'receipt_date' => now()->toDateString(),
        'amount' => 100_000,
    ], [
        ['target_id' => $receivable->id, 'amount' => 100_000],
    ]);

    $category = makeExpenseCategory($company->id);

    $expense = app(ExpenseService::class)->save(null, [
        'expense_category_id' => $category->id,
        'cash_account_id' => $account->id,
        'expense_date' => now()->toDateString(),
        'amount' => 300_000,
        'payee' => 'Bengkel Jaya',
    ]);

    $pages = [
        route('finance.cash.index'),
        route('finance.cash.create'),
        route('finance.cash.edit', $account),
        route('finance.cash.detail', $account),
        route('finance.receivables.index'),
        route('finance.receivables.aging'),
        route('finance.receipts.index'),
        route('finance.receipts.create'),
        route('finance.receipts.edit', $receipt),
        route('finance.receipts.detail', $receipt),
        route('finance.payables.index'),
        route('finance.payables.aging'),
        route('finance.payments.index'),
        route('finance.payments.create'),
        route('finance.expenses.index'),
        route('finance.expenses.create'),
        route('finance.expenses.edit', $expense),
        route('finance.expenses.detail', $expense),
    ];

    foreach ($pages as $page) {
        $this->actingAs($owner)->get($page)->assertOk();
    }
});

it('menyajikan data DataTables server-side untuk dokumen keuangan', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();
    $owner = $this->makeUser($company, 'Owner', $branch);

    buildReceivable($company->id, $warehouse->id, $branch->id);

    $account = makeCashAccount($company->id);
    app(CashService::class)->record($account, 'in', 1_000_000, 'opening', 'Saldo awal');

    $endpoints = [
        route('finance.cash.data'),
        route('finance.receivables.data'),
        route('finance.receipts.data'),
        route('finance.payables.data'),
        route('finance.payments.data'),
        route('finance.expenses.data'),
    ];

    foreach ($endpoints as $endpoint) {
        $this->actingAs($owner)
            ->post($endpoint, ['draw' => 1, 'start' => 0, 'length' => 10])
            ->assertOk()
            ->assertJsonPath('draw', 1);
    }

    $this->actingAs($owner)
        ->post(route('finance.receivables.data'), ['draw' => 1, 'start' => 0, 'length' => 10])
        ->assertJsonPath('recordsTotal', 1);

    $this->actingAs($owner)
        ->post(route('finance.cash.data'), ['draw' => 1, 'start' => 0, 'length' => 10])
        ->assertJsonPath('recordsTotal', 1);
});

it('menambah akun kas beserta mutasi saldo awalnya', function (): void {
    ['company' => $company, 'branch' => $branch] = $this->bootCompany();
    $finance = $this->makeUser($company, 'Finance', $branch);

    $this->actingAs($finance)
        ->post(route('finance.cash.store'), [
            'code' => 'BANK-UJI',
            'name' => 'BCA Cabang Bengkulu',
            'type' => 'bank',
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'opening_balance' => 7_500_000,
            'is_active' => 1,
        ])
        ->assertRedirect(route('finance.cash.index'));

    $account = CashAccount::where('code', 'BANK-UJI')->firstOrFail();

    expect($account->balance)->toBe(7_500_000.0);
    expect($account->transactions()->count())->toBe(1);
    expect($account->transactions()->first()->category)->toBe('opening');
});

it('mencatat biaya lewat form dan mengajukannya sekaligus', function (): void {
    ['company' => $company, 'branch' => $branch] = $this->bootCompany();
    $finance = $this->makeUser($company, 'Finance', $branch);

    $category = makeExpenseCategory($company->id, ['requires_approval' => false]);
    $account = makeCashAccount($company->id);

    $this->actingAs($finance)
        ->post(route('finance.expenses.store'), [
            'expense_category_id' => $category->id,
            'cash_account_id' => $account->id,
            'expense_date' => now()->toDateString(),
            'amount' => 450_000,
            'tax_amount' => 0,
            'payee' => 'CV Sumber Air',
            'description' => 'Air minum kantor',
            'submit' => 1,
        ])
        ->assertRedirect();

    $expense = Expense::firstOrFail();

    expect($expense->total)->toBe(450_000.0);
    expect($expense->status->value)->toBe('approved');
});

it('menolak akses keuangan untuk user tanpa permission', function (): void {
    ['company' => $company, 'branch' => $branch] = $this->bootCompany();
    $salesman = $this->makeUser($company, 'Salesman', $branch);

    $this->actingAs($salesman)->get(route('finance.cash.index'))->assertForbidden();
    $this->actingAs($salesman)->get(route('finance.payables.index'))->assertForbidden();
    $this->actingAs($salesman)->get(route('finance.expenses.create'))->assertForbidden();
});
