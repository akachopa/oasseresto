<?php

declare(strict_types=1);

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Models\ApprovalRule;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Finance\Services\CashService;
use App\Modules\Finance\Services\ExpenseService;

it('menyetujui biaya kecil tanpa approval dan memposting ke kas', function (): void {
    ['company' => $company] = $this->bootCompany();

    $category = makeExpenseCategory($company->id, ['approval_threshold' => 1_000_000]);
    $account = makeCashAccount($company->id);

    app(CashService::class)->record($account, 'in', 2_000_000, 'opening', 'Saldo awal');

    $expenses = app(ExpenseService::class);

    $expense = $expenses->save(null, [
        'expense_category_id' => $category->id,
        'cash_account_id' => $account->id,
        'expense_date' => now()->toDateString(),
        'amount' => 250_000,
        'payee' => 'PLN',
        'description' => 'Listrik gudang',
    ]);

    expect($expense->total)->toBe(250_000.0);

    $expenses->submit($expense);

    expect($expense->fresh()->status)->toBe(DocumentStatus::Approved);

    $expenses->post($expense->fresh());

    expect($expense->fresh()->status)->toBe(DocumentStatus::Posted);
    expect($account->fresh()->balance)->toBe(1_750_000.0);
});

it('meminta approval untuk biaya di atas ambang kategori', function (): void {
    ['company' => $company, 'branch' => $branch] = $this->bootCompany();

    $manager = $this->makeUser($company, 'Branch Manager', $branch);
    $category = makeExpenseCategory($company->id, ['approval_threshold' => 500_000]);
    $account = makeCashAccount($company->id);

    ApprovalRule::create([
        'company_id' => $company->id,
        'document_type' => 'expense',
        'name' => 'Biaya besar',
        'sequence' => 1,
        'min_amount' => 500_000,
        'approver_role' => 'Branch Manager',
    ]);

    $expenses = app(ExpenseService::class);

    $expense = $expenses->save(null, [
        'expense_category_id' => $category->id,
        'cash_account_id' => $account->id,
        'expense_date' => now()->toDateString(),
        'amount' => 2_500_000,
        'description' => 'Servis kendaraan operasional',
    ]);

    $expenses->submit($expense);

    expect($expense->fresh()->status)->toBe(DocumentStatus::Submitted);
    expect(fn () => $expenses->post($expense->fresh()))->toThrow(RuntimeException::class);

    $approval = ApprovalRequest::where('document_type', 'expense')
        ->where('document_id', $expense->id)
        ->firstOrFail();

    app(ApprovalService::class)->approve($approval, $manager);

    expect($expense->fresh()->status)->toBe(DocumentStatus::Approved);
});

it('membalik mutasi kas saat biaya yang sudah diposting dibatalkan', function (): void {
    ['company' => $company] = $this->bootCompany();

    $category = makeExpenseCategory($company->id, ['requires_approval' => false]);
    $account = makeCashAccount($company->id);

    app(CashService::class)->record($account, 'in', 1_000_000, 'opening', 'Saldo awal');

    $expenses = app(ExpenseService::class);

    $expense = $expenses->save(null, [
        'expense_category_id' => $category->id,
        'cash_account_id' => $account->id,
        'expense_date' => now()->toDateString(),
        'amount' => 400_000,
    ]);

    $expenses->submit($expense);
    $expenses->post($expense->fresh());

    expect($account->fresh()->balance)->toBe(600_000.0);

    $expenses->cancel($expense->fresh());

    expect($expense->fresh()->status)->toBe(DocumentStatus::Cancelled);
    expect($account->fresh()->balance)->toBe(1_000_000.0);
});

it('menolak posting biaya tanpa akun kas', function (): void {
    ['company' => $company] = $this->bootCompany();

    $category = makeExpenseCategory($company->id, ['requires_approval' => false]);
    $expenses = app(ExpenseService::class);

    $expense = $expenses->save(null, [
        'expense_category_id' => $category->id,
        'expense_date' => now()->toDateString(),
        'amount' => 100_000,
    ]);

    $expenses->submit($expense);

    expect(fn () => $expenses->post($expense->fresh()))->toThrow(RuntimeException::class);
});
