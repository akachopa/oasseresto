<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Accounting\Models\Account;
use App\Modules\Company\Models\Company;
use App\Modules\Core\Enums\AccountType;
use Illuminate\Database\Seeder;

/**
 * Template COA default (PLAN 38). Kolom slug dipakai oleh
 * AccountingPostingService supaya posting tidak bergantung pada nomor akun
 * yang bisa diubah company.
 */
class AccountSeeder extends Seeder
{
    /**
     * @return array<int, array{code: string, name: string, type: AccountType, slug: ?string, parent: ?string, postable: bool}>
     */
    public static function template(): array
    {
        $rows = [
            // Aset
            ['1000', 'ASET', AccountType::Asset, null, null, false],
            ['1100', 'Aset Lancar', AccountType::Asset, null, '1000', false],
            ['1110', 'Kas', AccountType::Asset, 'cash', '1100', true],
            ['1120', 'Bank', AccountType::Asset, 'bank', '1100', true],
            ['1130', 'Piutang Usaha', AccountType::Asset, 'accounts_receivable', '1100', true],
            ['1135', 'Piutang Lain-lain', AccountType::Asset, 'other_receivable', '1100', true],
            ['1140', 'Persediaan Barang Dagang', AccountType::Asset, 'inventory', '1100', true],
            ['1145', 'Persediaan Dalam Perjalanan', AccountType::Asset, 'goods_in_transit', '1100', true],
            ['1150', 'Biaya Dibayar Dimuka', AccountType::Asset, 'prepaid_expense', '1100', true],
            ['1160', 'PPN Masukan', AccountType::Asset, 'vat_input', '1100', true],
            ['1200', 'Aset Tetap', AccountType::Asset, null, '1000', false],
            ['1210', 'Tanah & Bangunan', AccountType::Asset, 'fixed_asset_building', '1200', true],
            ['1220', 'Kendaraan', AccountType::Asset, 'fixed_asset_vehicle', '1200', true],
            ['1230', 'Peralatan & Mesin', AccountType::Asset, 'fixed_asset_equipment', '1200', true],
            ['1290', 'Akumulasi Penyusutan', AccountType::Asset, 'accumulated_depreciation', '1200', true],

            // Kewajiban
            ['2000', 'KEWAJIBAN', AccountType::Liability, null, null, false],
            ['2100', 'Kewajiban Jangka Pendek', AccountType::Liability, null, '2000', false],
            ['2110', 'Hutang Usaha', AccountType::Liability, 'accounts_payable', '2100', true],
            ['2120', 'Hutang Gaji', AccountType::Liability, 'salary_payable', '2100', true],
            ['2130', 'Hutang Pajak', AccountType::Liability, 'tax_payable', '2100', true],
            ['2135', 'PPN Keluaran', AccountType::Liability, 'vat_output', '2100', true],
            ['2140', 'Hutang Lain-lain', AccountType::Liability, 'other_payable', '2100', true],
            ['2150', 'Penerimaan Dimuka', AccountType::Liability, 'customer_deposit', '2100', true],
            ['2160', 'Penerimaan Barang Belum Difaktur', AccountType::Liability, 'grni', '2100', true],

            // Ekuitas
            ['3000', 'EKUITAS', AccountType::Equity, null, null, false],
            ['3100', 'Modal Disetor', AccountType::Equity, 'capital', '3000', true],
            ['3200', 'Laba Ditahan', AccountType::Equity, 'retained_earnings', '3000', true],
            ['3300', 'Laba Tahun Berjalan', AccountType::Equity, 'current_year_earnings', '3000', true],

            // Pendapatan
            ['4000', 'PENDAPATAN', AccountType::Revenue, null, null, false],
            ['4100', 'Penjualan', AccountType::Revenue, 'sales_revenue', '4000', true],
            ['4200', 'Diskon Penjualan', AccountType::Revenue, 'sales_discount', '4000', true],
            ['4300', 'Retur Penjualan', AccountType::Revenue, 'sales_return', '4000', true],

            // HPP
            ['5000', 'HARGA POKOK PENJUALAN', AccountType::Cogs, null, null, false],
            ['5100', 'Harga Pokok Penjualan', AccountType::Cogs, 'cogs', '5000', true],
            ['5200', 'Selisih Persediaan', AccountType::Cogs, 'inventory_variance', '5000', true],
            ['5300', 'Barang Rusak & Kedaluwarsa', AccountType::Cogs, 'inventory_write_off', '5000', true],

            // Beban operasional
            ['6000', 'BEBAN OPERASIONAL', AccountType::Expense, null, null, false],
            ['6100', 'Beban Gaji', AccountType::Expense, 'expense_salary', '6000', true],
            ['6105', 'Beban Tunjangan', AccountType::Expense, 'expense_allowance', '6000', true],
            ['6110', 'Beban Lembur', AccountType::Expense, 'expense_overtime', '6000', true],
            ['6120', 'Beban Sewa', AccountType::Expense, 'expense_rent', '6000', true],
            ['6130', 'Beban Listrik', AccountType::Expense, 'expense_electricity', '6000', true],
            ['6135', 'Beban Air', AccountType::Expense, 'expense_water', '6000', true],
            ['6140', 'Beban Internet & Telepon', AccountType::Expense, 'expense_internet', '6000', true],
            ['6150', 'Beban Bahan Bakar', AccountType::Expense, 'expense_fuel', '6000', true],
            ['6155', 'Beban Transportasi', AccountType::Expense, 'expense_transportation', '6000', true],
            ['6160', 'Beban Pemasaran', AccountType::Expense, 'expense_marketing', '6000', true],
            ['6165', 'Beban Gudang', AccountType::Expense, 'expense_warehouse', '6000', true],
            ['6170', 'Beban Packaging', AccountType::Expense, 'expense_packaging', '6000', true],
            ['6175', 'Beban Pemeliharaan', AccountType::Expense, 'expense_maintenance', '6000', true],
            ['6180', 'Beban Keamanan', AccountType::Expense, 'expense_security', '6000', true],
            ['6185', 'Beban Kebersihan', AccountType::Expense, 'expense_cleaning', '6000', true],
            ['6190', 'Beban Administrasi', AccountType::Expense, 'expense_admin', '6000', true],
            ['6195', 'Beban Pajak', AccountType::Expense, 'expense_tax', '6000', true],
            ['6200', 'Beban Penyusutan', AccountType::Expense, 'depreciation_expense', '6000', true],
            ['6900', 'Beban Lain-lain', AccountType::Expense, 'expense_other', '6000', true],

            // Non operasional
            ['7000', 'PENDAPATAN LAIN', AccountType::OtherIncome, null, null, false],
            ['7100', 'Pendapatan Bunga', AccountType::OtherIncome, 'interest_income', '7000', true],
            ['7200', 'Diskon Pembelian', AccountType::OtherIncome, 'purchase_discount', '7000', true],
            ['7300', 'Pendapatan Lain-lain', AccountType::OtherIncome, 'other_income', '7000', true],
            ['8000', 'BEBAN LAIN', AccountType::OtherExpense, null, null, false],
            ['8100', 'Beban Bunga & Administrasi Bank', AccountType::OtherExpense, 'bank_charge', '8000', true],
            ['8200', 'Kerugian Piutang', AccountType::OtherExpense, 'bad_debt_expense', '8000', true],
            ['8300', 'Beban Lain-lain', AccountType::OtherExpense, 'other_expense', '8000', true],
        ];

        return array_map(fn (array $row) => [
            'code' => $row[0],
            'name' => $row[1],
            'type' => $row[2],
            'slug' => $row[3],
            'parent' => $row[4],
            'postable' => $row[5],
        ], $rows);
    }

    public function run(): void
    {
        Company::query()->each(function (Company $company): void {
            $parents = [];

            foreach (self::template() as $row) {
                $account = Account::withoutGlobalScopes()->updateOrCreate(
                    ['company_id' => $company->id, 'code' => $row['code']],
                    [
                        'parent_id' => $row['parent'] ? ($parents[$row['parent']] ?? null) : null,
                        'name' => $row['name'],
                        'type' => $row['type'],
                        'slug' => $row['slug'],
                        'is_postable' => $row['postable'],
                        'is_system' => $row['slug'] !== null,
                        'is_active' => true,
                    ],
                );

                $parents[$row['code']] = $account->id;
            }
        });
    }
}
