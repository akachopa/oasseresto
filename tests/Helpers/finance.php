<?php

declare(strict_types=1);

use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\ExpenseCategory;

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
