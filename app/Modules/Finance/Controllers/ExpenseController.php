<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Models\CostCenter;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\Finance\Services\ExpenseService;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ExpenseController
{
    public function __construct(
        private readonly ExpenseService $expenses,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        return view('finance.expense.index', [
            'categories' => ExpenseCategory::active()->orderBy('name')->pluck('name', 'id'),
            'statuses' => self::statusOptions(),
            'monthTotal' => (float) Expense::query()
                ->whereIn('status', [DocumentStatus::Approved, DocumentStatus::Posted])
                ->whereBetween('expense_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->sum('total'),
            'pendingTotal' => (float) Expense::query()
                ->where('status', DocumentStatus::Submitted)
                ->sum('total'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Expense::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->leftJoin('cash_accounts', 'cash_accounts.id', '=', 'expenses.cash_account_id')
            ->select([
                'expenses.*',
                'expense_categories.name as category_name',
                'cash_accounts.name as account_name',
            ]);

        $this->location->applyBranch($query, 'expenses.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable([
                    'expenses.number', 'expenses.payee', 'expenses.description', 'expense_categories.name',
                ])
                ->orderable([
                    null, 'expenses.number', 'expenses.expense_date',
                    'expense_categories.name', 'expenses.payee',
                    'expenses.total', 'expenses.status', null,
                ])
                ->filter('category', fn ($q, $value) => $q->where('expenses.expense_category_id', $value))
                ->filter('status', fn ($q, $value) => $q->where('expenses.status', $value))
                ->transform(fn (Expense $expense) => [
                    'number' => '<a class="font-mono text-xs hover:text-brand-600" href="'
                        .route('finance.expenses.detail', $expense).'">'.e($expense->number).'</a>',
                    'date' => $expense->expense_date->format('d/m/Y'),
                    'category' => e((string) $expense->category_name),
                    'payee' => e((string) ($expense->payee ?: '-')),
                    'total' => Money::rupiah($expense->total),
                    'status' => view('components.status-badge', ['status' => $expense->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'detail' => route('finance.expenses.detail', $expense),
                        'edit' => $expense->status->isEditable() ? route('finance.expenses.edit', $expense) : null,
                        'delete' => $expense->status !== DocumentStatus::Cancelled
                            ? route('finance.expenses.hapus', $expense)
                            : null,
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(): View
    {
        return view('finance.expense.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateExpense($request);

        try {
            $expense = $this->expenses->save(null, $validated);

            if ($request->boolean('submit')) {
                $this->expenses->submit($expense);
            }
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()
            ->route('finance.expenses.detail', $expense)
            ->with('status', 'Biaya disimpan.');
    }

    public function edit(Expense $expense): View
    {
        abort_unless($expense->status->isEditable(), 403);

        return view('finance.expense.edit', array_merge($this->formOptions(), ['expense' => $expense]));
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $validated = $this->validateExpense($request);

        try {
            $this->expenses->save($expense, $validated);

            if ($request->boolean('submit')) {
                $this->expenses->submit($expense->fresh());
            }
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()
            ->route('finance.expenses.detail', $expense)
            ->with('status', 'Biaya diperbarui.');
    }

    public function detail(Expense $expense): View
    {
        $expense->load(['category', 'cashAccount', 'costCenter', 'supplier', 'creator', 'approver']);

        return view('finance.expense.detail', [
            'expense' => $expense,
            'accounts' => CashAccount::active()->orderBy('name')->pluck('name', 'id'),
            'approval' => ApprovalRequest::where('document_type', $expense->approvalDocumentType())
                ->where('document_id', $expense->getKey())
                ->with('steps.approver', 'steps.actor')
                ->latest('id')
                ->first(),
        ]);
    }

    public function submit(Expense $expense): RedirectResponse
    {
        try {
            $this->expenses->submit($expense);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Biaya diajukan.');
    }

    public function approve(Expense $expense, Request $request): RedirectResponse
    {
        try {
            $this->expenses->approve($expense, $request->string('note')->toString() ?: null);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Biaya disetujui.');
    }

    public function reject(Expense $expense, Request $request): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $this->expenses->reject($expense, $validated['reason']);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Biaya ditolak.');
    }

    public function post(Request $request, Expense $expense): RedirectResponse
    {
        $validated = $request->validate([
            'cash_account_id' => ['required', 'integer', 'exists:cash_accounts,id'],
        ]);

        try {
            $expense->cash_account_id = (int) $validated['cash_account_id'];
            $expense->save();

            $this->expenses->post($expense);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Biaya diposting dan kas berkurang.');
    }

    public function hapus(Expense $expense): RedirectResponse
    {
        try {
            $this->expenses->cancel($expense);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Biaya dibatalkan.');
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            DocumentStatus::Draft->value => DocumentStatus::Draft->label(),
            DocumentStatus::Submitted->value => 'Menunggu Approval',
            DocumentStatus::Approved->value => 'Disetujui',
            DocumentStatus::Posted->value => 'Dibayar',
            DocumentStatus::Rejected->value => DocumentStatus::Rejected->label(),
            DocumentStatus::Cancelled->value => DocumentStatus::Cancelled->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => ExpenseCategory::active()->orderBy('name')->get(),
            'accounts' => CashAccount::active()->orderBy('name')->pluck('name', 'id'),
            'costCenters' => CostCenter::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'suppliers' => Supplier::active()->orderBy('name')->pluck('name', 'id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateExpense(Request $request): array
    {
        return $request->validate([
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'cash_account_id' => ['nullable', 'integer', 'exists:cash_accounts,id'],
            'cost_center_id' => ['nullable', 'integer', 'exists:cost_centers,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'expense_date' => ['required', 'date'],
            'payee' => ['nullable', 'string', 'max:150'],
            'reference' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
