<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Cogs = 'cogs';
    case Expense = 'expense';
    case OtherIncome = 'other_income';
    case OtherExpense = 'other_expense';

    /**
     * Sisi normal saldo akun: debit atau credit.
     */
    public function normalBalance(): string
    {
        return match ($this) {
            self::Asset, self::Cogs, self::Expense, self::OtherExpense => 'debit',
            self::Liability, self::Equity, self::Revenue, self::OtherIncome => 'credit',
        };
    }

    public function isBalanceSheet(): bool
    {
        return in_array($this, [self::Asset, self::Liability, self::Equity], true);
    }

    public function isProfitLoss(): bool
    {
        return ! $this->isBalanceSheet();
    }

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Aset',
            self::Liability => 'Kewajiban',
            self::Equity => 'Ekuitas',
            self::Revenue => 'Pendapatan',
            self::Cogs => 'Harga Pokok Penjualan',
            self::Expense => 'Beban Operasional',
            self::OtherIncome => 'Pendapatan Lain',
            self::OtherExpense => 'Beban Lain',
        };
    }
}
