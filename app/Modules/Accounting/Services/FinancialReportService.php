<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Company\Models\Company;
use App\Modules\Core\Enums\AccountType;
use App\Modules\Core\Enums\JournalStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FinancialReportService
{
    /**
     * @return array{rows: Collection<int, object>, total_debit: float, total_credit: float, balanced: bool}
     */
    public function trialBalance(Company $company, Carbon $from, Carbon $to): array
    {
        $rows = $this->accountTotals($company, $from, $to)
            ->filter(fn (object $row) => abs($row->debit) >= 0.0001 || abs($row->credit) >= 0.0001)
            ->values();

        $totalDebit = round((float) $rows->sum('debit'), 4);
        $totalCredit = round((float) $rows->sum('credit'), 4);

        return [
            'rows' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balanced' => abs($totalDebit - $totalCredit) < 0.0001,
        ];
    }

    /**
     * @return array{rows: Collection<int, object>, opening: float, closing: float}
     */
    public function generalLedger(Company $company, Account $account, Carbon $from, Carbon $to): array
    {
        $opening = $this->balanceAsOf($company, $account, $from->copy()->subDay());

        $lines = JournalLine::query()
            ->select('journal_lines.*')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.company_id', $company->id)
            ->where('journal_lines.account_id', $account->id)
            ->whereIn('journal_entries.status', [JournalStatus::Posted, JournalStatus::Reversed])
            ->whereDate('journal_entries.entry_date', '>=', $from->toDateString())
            ->whereDate('journal_entries.entry_date', '<=', $to->toDateString())
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.sequence')
            ->with('entry')
            ->get();

        $running = $opening;
        $normal = $account->type->normalBalance();

        $rows = $lines->map(function (JournalLine $line) use (&$running, $normal): object {
            $delta = $normal === 'credit'
                ? $line->credit - $line->debit
                : $line->debit - $line->credit;
            $running = round($running + $delta, 4);

            return (object) [
                'date' => $line->entry->entry_date,
                'number' => $line->entry->number,
                'description' => $line->memo ?: $line->entry->description,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'balance' => $running,
            ];
        });

        return [
            'rows' => $rows,
            'opening' => $opening,
            'closing' => $running,
        ];
    }

    /**
     * @return array{groups: array<string, array{label: string, rows: Collection, total: float}>, net_income: float}
     */
    public function profitLoss(Company $company, Carbon $from, Carbon $to): array
    {
        $totals = $this->accountTotals($company, $from, $to);
        $groups = [];

        foreach ([
            AccountType::Revenue,
            AccountType::Cogs,
            AccountType::Expense,
            AccountType::OtherIncome,
            AccountType::OtherExpense,
        ] as $type) {
            $rows = $totals->filter(fn (object $row) => $row->type === $type)->values();
            $groups[$type->value] = [
                'label' => $type->label(),
                'rows' => $rows,
                'total' => round((float) $rows->sum('signed'), 4),
            ];
        }

        $net = round(
            $groups['revenue']['total']
            - $groups['cogs']['total']
            - $groups['expense']['total']
            + $groups['other_income']['total']
            - $groups['other_expense']['total'],
            4,
        );

        return ['groups' => $groups, 'net_income' => $net];
    }

    /**
     * @return array{groups: array<string, array{label: string, rows: Collection, total: float}>, net_income: float, balanced: bool}
     */
    public function balanceSheet(Company $company, Carbon $asOf): array
    {
        $from = Carbon::parse('1970-01-01');
        $totals = $this->accountTotals($company, $from, $asOf);
        $pnl = $this->profitLoss($company, Carbon::parse($asOf->year.'-01-01'), $asOf);
        $groups = [];

        foreach ([AccountType::Asset, AccountType::Liability, AccountType::Equity] as $type) {
            $rows = $totals->filter(fn (object $row) => $row->type === $type)->values();
            $groups[$type->value] = [
                'label' => $type->label(),
                'rows' => $rows,
                'total' => round((float) $rows->sum('signed'), 4),
            ];
        }

        $groups['equity']['total'] = round($groups['equity']['total'] + $pnl['net_income'], 4);
        $assets = $groups['asset']['total'];
        $liabilitiesAndEquity = round($groups['liability']['total'] + $groups['equity']['total'], 4);

        return [
            'groups' => $groups,
            'net_income' => $pnl['net_income'],
            'balanced' => abs($assets - $liabilitiesAndEquity) < 0.0001,
        ];
    }

    /**
     * Arus kas langsung: mutasi akun kas/bank dikelompokkan menurut tujuan jurnal.
     *
     * @return array{operating: float, investing: float, financing: float, net: float, rows: Collection}
     */
    public function cashFlow(Company $company, Carbon $from, Carbon $to): array
    {
        $cashIds = Account::query()
            ->where('company_id', $company->id)
            ->whereIn('slug', ['cash', 'bank'])
            ->pluck('id');

        $lines = JournalLine::query()
            ->select('journal_lines.*', 'journal_entries.purpose', 'journal_entries.entry_date', 'journal_entries.description', 'journal_entries.number')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.company_id', $company->id)
            ->whereIn('journal_lines.account_id', $cashIds)
            ->whereIn('journal_entries.status', [JournalStatus::Posted, JournalStatus::Reversed])
            ->whereDate('journal_entries.entry_date', '>=', $from->toDateString())
            ->whereDate('journal_entries.entry_date', '<=', $to->toDateString())
            ->orderBy('journal_entries.entry_date')
            ->get();

        $rows = $lines->map(function (JournalLine $line): object {
            $net = round($line->debit - $line->credit, 4);
            $section = $this->cashFlowSection((string) $line->purpose);

            return (object) [
                'date' => $line->entry_date,
                'number' => $line->number,
                'description' => $line->description,
                'section' => $section,
                'amount' => $net,
            ];
        });

        $operating = round((float) $rows->where('section', 'operating')->sum('amount'), 4);
        $investing = round((float) $rows->where('section', 'investing')->sum('amount'), 4);
        $financing = round((float) $rows->where('section', 'financing')->sum('amount'), 4);

        return [
            'operating' => $operating,
            'investing' => $investing,
            'financing' => $financing,
            'net' => round($operating + $investing + $financing, 4),
            'rows' => $rows,
        ];
    }

    private function cashFlowSection(string $purpose): string
    {
        $purpose = str_replace(':reversal', '', $purpose);

        return match ($purpose) {
            'cash_opening', 'cash_transfer' => 'financing',
            'stock_adjustment', 'stock_opname' => 'investing',
            default => 'operating',
        };
    }

    /**
     * @return Collection<int, object>
     */
    private function accountTotals(Company $company, Carbon $from, Carbon $to): Collection
    {
        $accounts = Account::query()
            ->where('company_id', $company->id)
            ->where('is_postable', true)
            ->orderBy('code')
            ->get();

        $sums = JournalLine::query()
            ->selectRaw('journal_lines.account_id, COALESCE(SUM(journal_lines.debit),0) as debit, COALESCE(SUM(journal_lines.credit),0) as credit')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.company_id', $company->id)
            ->whereIn('journal_entries.status', [JournalStatus::Posted, JournalStatus::Reversed])
            ->whereDate('journal_entries.entry_date', '>=', $from->toDateString())
            ->whereDate('journal_entries.entry_date', '<=', $to->toDateString())
            ->groupBy('journal_lines.account_id')
            ->get()
            ->keyBy('account_id');

        return $accounts->map(function (Account $account) use ($sums): object {
            $sum = $sums->get($account->id);
            $debit = round((float) ($sum->debit ?? 0), 4);
            $credit = round((float) ($sum->credit ?? 0), 4);
            $signed = $account->type->normalBalance() === 'credit'
                ? round($credit - $debit, 4)
                : round($debit - $credit, 4);

            return (object) [
                'account' => $account,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'debit' => $debit,
                'credit' => $credit,
                'signed' => $signed,
            ];
        });
    }

    private function balanceAsOf(Company $company, Account $account, Carbon $asOf): float
    {
        $row = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.company_id', $company->id)
            ->where('journal_lines.account_id', $account->id)
            ->whereIn('journal_entries.status', [JournalStatus::Posted, JournalStatus::Reversed])
            ->whereDate('journal_entries.entry_date', '<=', $asOf->toDateString())
            ->selectRaw('COALESCE(SUM(journal_lines.debit),0) as debit, COALESCE(SUM(journal_lines.credit),0) as credit')
            ->first();

        $debit = (float) ($row->debit ?? 0);
        $credit = (float) ($row->credit ?? 0);

        return $account->type->normalBalance() === 'credit'
            ? round($credit - $debit, 4)
            : round($debit - $credit, 4);
    }
}
