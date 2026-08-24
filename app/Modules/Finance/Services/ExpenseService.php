<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Services\DocumentNumberService;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\ExpenseCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Biaya operasional (PLAN 36). Biaya di atas ambang kategori wajib melewati
 * approval, dan kas baru berkurang saat biaya diposting.
 */
class ExpenseService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly ApprovalService $approvals,
        private readonly CashService $cash,
        private readonly ScopeManager $scope,
    ) {}

    public function save(?Expense $expense, array $attributes): Expense
    {
        return DB::transaction(function () use ($expense, $attributes): Expense {
            if ($expense !== null && ! $expense->status->isEditable()) {
                throw new RuntimeException('Biaya yang sudah diajukan tidak bisa diubah.');
            }

            $category = ExpenseCategory::findOrFail($attributes['expense_category_id']);
            $date = Carbon::parse($attributes['expense_date']);
            $amount = round((float) $attributes['amount'], 4);
            $tax = round((float) ($attributes['tax_amount'] ?? 0), 4);

            $expense ??= new Expense([
                'status' => DocumentStatus::Draft,
                'created_by' => Auth::id(),
            ]);

            $expense->fill([
                'expense_category_id' => $category->getKey(),
                'cash_account_id' => $attributes['cash_account_id'] ?? null,
                'cost_center_id' => $attributes['cost_center_id'] ?? null,
                'supplier_id' => $attributes['supplier_id'] ?? null,
                'branch_id' => $attributes['branch_id'] ?? $this->scope->branchId(),
                'expense_date' => $date->toDateString(),
                'payee' => $attributes['payee'] ?? null,
                'reference' => $attributes['reference'] ?? null,
                'amount' => $amount,
                'tax_amount' => $tax,
                'total' => round($amount + $tax, 4),
                'description' => $attributes['description'] ?? null,
                'attachment_path' => $attributes['attachment_path'] ?? $expense->attachment_path,
            ]);

            $expense->number ??= $this->numbers->next('expense', $expense->branch_id, $date);
            $expense->save();

            return $expense->refresh();
        });
    }

    /**
     * Ajukan biaya. Kategori yang tidak memerlukan approval langsung disetujui
     * supaya biaya kecil tidak menumpuk di antrian.
     */
    public function submit(Expense $expense): Expense
    {
        return DB::transaction(function () use ($expense): Expense {
            if (! $expense->status->isEditable()) {
                throw new RuntimeException('Biaya ini sudah diajukan.');
            }

            $expense->load('category');

            $expense->status = DocumentStatus::Submitted;
            $expense->submitted_at = now();
            $expense->save();

            $needsApproval = $expense->category?->needsApproval((float) $expense->total) ?? true;
            $approval = $needsApproval ? $this->approvals->request($expense) : null;

            if ($approval === null) {
                $expense->status = DocumentStatus::Approved;
                $expense->approved_by = Auth::id();
                $expense->approved_at = now();
                $expense->save();
            }

            return $expense->refresh();
        });
    }

    public function approve(Expense $expense, ?string $note = null): Expense
    {
        $approval = $this->openApproval($expense);

        if ($approval === null) {
            if ($expense->status !== DocumentStatus::Submitted) {
                throw new RuntimeException('Hanya biaya yang diajukan bisa disetujui.');
            }

            $expense->status = DocumentStatus::Approved;
            $expense->approved_by = Auth::id();
            $expense->approved_at = now();
            $expense->save();

            return $expense;
        }

        $this->approvals->approve($approval, Auth::user(), $note);

        return $expense->refresh();
    }

    public function reject(Expense $expense, ?string $reason = null): Expense
    {
        $approval = $this->openApproval($expense);

        if ($approval !== null) {
            $this->approvals->reject($approval, Auth::user(), $reason);

            return $expense->refresh();
        }

        $expense->status = DocumentStatus::Rejected;
        $expense->rejection_reason = $reason;
        $expense->save();

        return $expense;
    }

    /**
     * Posting biaya: kas berkurang sesuai akun yang dipilih.
     */
    public function post(Expense $expense): Expense
    {
        return DB::transaction(function () use ($expense): Expense {
            if (! $expense->isPayable()) {
                throw new RuntimeException('Hanya biaya yang sudah disetujui bisa diposting.');
            }

            $account = $expense->cash_account_id ? CashAccount::find($expense->cash_account_id) : null;

            if ($account === null) {
                throw new RuntimeException('Pilih kas atau bank pembayar sebelum memposting biaya.');
            }

            $this->cash->record(
                account: $account,
                direction: 'out',
                amount: (float) $expense->total,
                category: 'expense',
                description: $expense->description ?: ($expense->category?->name ?? 'Biaya'),
                documentType: 'expense',
                documentId: (int) $expense->getKey(),
                documentNumber: $expense->number,
                date: $expense->expense_date,
            );

            $expense->cash_account_id = $account->getKey();
            $expense->status = DocumentStatus::Posted;
            $expense->posted_by = Auth::id();
            $expense->posted_at = now();
            $expense->save();

            return $expense->refresh();
        });
    }

    public function cancel(Expense $expense): Expense
    {
        return DB::transaction(function () use ($expense): Expense {
            if ($expense->isPosted()) {
                $this->cash->reverseDocument('expense', (int) $expense->getKey(), 'Pembatalan '.$expense->number);
            }

            $this->approvals->cancelOpenRequests($expense);

            $expense->status = DocumentStatus::Cancelled;
            $expense->save();

            return $expense;
        });
    }

    private function openApproval(Expense $expense): ?ApprovalRequest
    {
        return ApprovalRequest::where('document_type', $expense->approvalDocumentType())
            ->where('document_id', $expense->getKey())
            ->pending()
            ->with('steps')
            ->first();
    }
}
