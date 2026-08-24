<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Accounting\Models\Account;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

/**
 * Kas, bank, dan kategori biaya minimum supaya modul keuangan bisa dipakai
 * sejak hari pertama tanpa setup manual.
 */
class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Company::all() as $company) {
            $this->cashAccounts($company);
            $this->expenseCategories($company);
        }
    }

    private function cashAccounts(Company $company): void
    {
        $branch = Branch::where('company_id', $company->id)->orderBy('id')->first();

        $accounts = [
            [
                'code' => 'KAS-01',
                'name' => 'Kas Besar',
                'type' => 'cash',
                'slug' => 'cash',
                'is_default' => true,
            ],
            [
                'code' => 'KAS-02',
                'name' => 'Kas Kecil Operasional',
                'type' => 'petty',
                'slug' => 'cash',
                'is_default' => false,
            ],
            [
                'code' => 'BANK-01',
                'name' => 'Bank Operasional',
                'type' => 'bank',
                'slug' => 'bank',
                'bank_name' => 'Bank Mandiri',
                'is_default' => false,
            ],
        ];

        foreach ($accounts as $row) {
            // Pemetaan ke COA memakai slug supaya tidak bergantung pada nomor
            // akun yang bisa diubah company.
            $accountId = Account::where('company_id', $company->id)
                ->where('slug', $row['slug'])
                ->value('id');

            CashAccount::firstOrCreate(
                ['company_id' => $company->id, 'code' => $row['code']],
                [
                    'branch_id' => $branch?->id,
                    'account_id' => $accountId,
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'bank_name' => $row['bank_name'] ?? null,
                    'is_default' => $row['is_default'],
                    'is_active' => true,
                ],
            );
        }
    }

    private function expenseCategories(Company $company): void
    {
        $categories = [
            ['code' => 'EXP-OPS', 'name' => 'Operasional Kantor', 'slug' => 'expense_admin', 'threshold' => 1_000_000],
            ['code' => 'EXP-UTIL', 'name' => 'Listrik, Air & Internet', 'slug' => 'expense_electricity', 'threshold' => 2_000_000],
            ['code' => 'EXP-TRANS', 'name' => 'Transportasi & Bahan Bakar', 'slug' => 'expense_fuel', 'threshold' => 500_000],
            ['code' => 'EXP-MAINT', 'name' => 'Perawatan & Perbaikan', 'slug' => 'expense_maintenance', 'threshold' => 1_000_000],
            ['code' => 'EXP-SALARY', 'name' => 'Gaji & Tunjangan', 'slug' => 'expense_salary', 'threshold' => 0],
            ['code' => 'EXP-MARKET', 'name' => 'Pemasaran & Promosi', 'slug' => 'expense_marketing', 'threshold' => 1_000_000],
            ['code' => 'EXP-OTHER', 'name' => 'Biaya Lain-lain', 'slug' => 'expense_other', 'threshold' => 250_000],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::firstOrCreate(
                ['company_id' => $company->id, 'code' => $category['code']],
                [
                    'account_id' => Account::where('company_id', $company->id)
                        ->where('slug', $category['slug'])
                        ->value('id'),
                    'name' => $category['name'],
                    'requires_approval' => true,
                    'approval_threshold' => $category['threshold'],
                    'is_active' => true,
                ],
            );
        }
    }
}
