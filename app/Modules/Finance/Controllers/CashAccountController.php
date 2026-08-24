<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Modules\Accounting\Models\Account;
use App\Modules\Company\Models\Branch;
use App\Modules\Core\Enums\AccountType;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\CashTransaction;
use App\Modules\Finance\Services\CashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class CashAccountController
{
    public function __construct(
        private readonly CashService $cash,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        $accounts = CashAccount::query()->orderBy('type')->orderBy('name')->get();

        return view('finance.cash.index', [
            'accounts' => $accounts,
            'total' => round((float) $accounts->where('is_active', true)->sum('balance'), 4),
            'types' => CashAccount::types(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = CashTransaction::query()
            ->join('cash_accounts', 'cash_accounts.id', '=', 'cash_transactions.cash_account_id')
            ->select(['cash_transactions.*', 'cash_accounts.name as account_name']);

        $this->location->applyBranch($query, 'cash_transactions.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable([
                    'cash_transactions.description', 'cash_transactions.document_number', 'cash_accounts.name',
                ])
                ->orderable([
                    null, 'cash_transactions.transaction_date', 'cash_accounts.name',
                    'cash_transactions.category', 'cash_transactions.document_number',
                    'cash_transactions.amount', 'cash_transactions.balance_after', null,
                ])
                ->filter('account', fn ($q, $value) => $q->where('cash_transactions.cash_account_id', $value))
                ->filter('category', fn ($q, $value) => $q->where('cash_transactions.category', $value))
                ->filter('direction', fn ($q, $value) => $q->where('cash_transactions.direction', $value))
                ->transform(fn (CashTransaction $row) => [
                    'date' => $row->transaction_date->format('d/m/Y'),
                    'account' => e((string) $row->account_name),
                    'category' => e($row->categoryLabel()),
                    'document' => $row->document_number
                        ? '<span class="font-mono text-xs">'.e($row->document_number).'</span>'
                        : '<span class="text-muted">'.e((string) $row->description).'</span>',
                    'amount' => ($row->isInbound() ? '<span class="text-positive">+' : '<span class="text-negative">-')
                        .Money::rupiah($row->amount).'</span>',
                    'balance' => Money::rupiah($row->balance_after),
                    'aksi' => '',
                ])
                ->make($request),
        );
    }

    public function create(): View
    {
        return view('finance.cash.create', [
            'types' => CashAccount::types(),
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'accounts' => $this->ledgerAccounts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateAccount($request);

        $account = new CashAccount($validated);
        $account->balance = 0;
        $account->save();

        // Saldo awal masuk sebagai mutasi pertama supaya buku kas lengkap
        // sejak baris pertama dan saldo akun terbentuk dari mutasi.
        if ($account->opening_balance > 0) {
            $this->cash->record(
                account: $account,
                direction: 'in',
                amount: $account->opening_balance,
                category: 'opening',
                description: 'Saldo awal '.$account->name,
            );
        }

        return redirect()->route('finance.cash.index')->with('status', 'Akun kas/bank ditambahkan.');
    }

    public function edit(CashAccount $cashAccount): View
    {
        return view('finance.cash.edit', [
            'account' => $cashAccount,
            'types' => CashAccount::types(),
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'accounts' => $this->ledgerAccounts(),
        ]);
    }

    public function update(Request $request, CashAccount $cashAccount): RedirectResponse
    {
        $validated = $this->validateAccount($request, $cashAccount);

        // Saldo awal tidak diubah dari sini karena buku kas sudah berjalan.
        unset($validated['opening_balance']);

        $cashAccount->fill($validated)->save();

        return redirect()->route('finance.cash.index')->with('status', 'Akun kas/bank diperbarui.');
    }

    public function detail(Request $request, CashAccount $cashAccount): View
    {
        $transactions = $cashAccount->transactions()
            ->latest('transaction_date')
            ->latest('id')
            ->limit(100)
            ->get();

        return view('finance.cash.detail', [
            'account' => $cashAccount,
            'transactions' => $transactions,
            'accounts' => CashAccount::active()->where('id', '!=', $cashAccount->id)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function transfer(Request $request, CashAccount $cashAccount): RedirectResponse
    {
        $validated = $request->validate([
            'to_cash_account_id' => ['required', 'integer', 'exists:cash_accounts,id', 'different:'.$cashAccount->getKey()],
            'amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            if ($cashAccount->balance + 0.0001 < (float) $validated['amount']) {
                throw new RuntimeException('Saldo akun asal tidak cukup untuk transfer ini.');
            }

            $this->cash->transfer(
                $cashAccount,
                CashAccount::findOrFail($validated['to_cash_account_id']),
                (float) $validated['amount'],
                $validated['note'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Transfer antar kas dicatat.');
    }

    public function hapus(CashAccount $cashAccount): RedirectResponse
    {
        if ($cashAccount->transactions()->exists()) {
            return back()->with('error', 'Akun yang sudah punya mutasi tidak bisa dihapus; nonaktifkan saja.');
        }

        $cashAccount->delete();

        return redirect()->route('finance.cash.index')->with('status', 'Akun kas/bank dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAccount(Request $request, ?CashAccount $account = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:30',
                'unique:cash_accounts,code,'.($account?->getKey() ?? 'NULL').',id,company_id,'.$request->user()->company_id,
            ],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:'.implode(',', array_keys(CashAccount::types()))],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:60'],
            'account_holder' => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'note' => ['nullable', 'string'],
        ]);
    }

    /**
     * Akun COA kas/bank untuk pemetaan jurnal nanti (PLAN 38).
     */
    private function ledgerAccounts()
    {
        return Account::query()
            ->where('is_active', true)
            ->where('type', AccountType::Asset)
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Account $account) => [$account->id => $account->code.' - '.$account->name]);
    }
}
