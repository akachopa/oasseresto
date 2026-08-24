<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Modules\Core\Enums\ReceivableStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Customer\Models\Customer;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Finance\Services\AgingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceivableController
{
    public function __construct(
        private readonly AgingService $aging,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        $summary = Receivable::query()
            ->outstanding()
            ->selectRaw('COALESCE(SUM(outstanding_amount), 0) as total, COUNT(*) as documents')
            ->first();

        $overdue = Receivable::query()
            ->outstanding()
            ->whereDate('due_date', '<', now()->toDateString())
            ->selectRaw('COALESCE(SUM(outstanding_amount), 0) as total, COUNT(*) as documents')
            ->first();

        return view('finance.receivable.index', [
            'customers' => Customer::active()->orderBy('name')->pluck('name', 'id'),
            'statuses' => self::statusOptions(),
            'outstandingTotal' => (float) $summary->total,
            'outstandingCount' => (int) $summary->documents,
            'overdueTotal' => (float) $overdue->total,
            'overdueCount' => (int) $overdue->documents,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Receivable::query()
            ->join('customers', 'customers.id', '=', 'receivables.customer_id')
            ->select(['receivables.*', 'customers.name as customer_name']);

        $this->location->applyBranch($query, 'receivables.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['receivables.document_number', 'customers.name', 'customers.code'])
                ->orderable([
                    null, 'receivables.document_number', 'receivables.invoice_date',
                    'customers.name', 'receivables.due_date', 'receivables.amount',
                    'receivables.outstanding_amount', 'receivables.status', null,
                ])
                ->filter('customer', fn ($q, $value) => $q->where('receivables.customer_id', $value))
                ->filter('status', fn ($q, $value) => $q->where('receivables.status', $value))
                ->filter('outstanding', fn ($q) => $q->outstanding())
                ->filter('overdue', fn ($q) => $q
                    ->outstanding()
                    ->whereDate('receivables.due_date', '<', now()->toDateString()))
                ->transform(fn (Receivable $row) => [
                    'document' => '<span class="font-mono text-xs">'.e($row->document_number).'</span>',
                    'date' => $row->invoice_date->format('d/m/Y'),
                    'customer' => e((string) $row->customer_name),
                    'due' => $row->due_date->format('d/m/Y')
                        .($row->isOverdue() ? ' <span class="badge-danger ml-1">'.$row->daysOverdue().' hari</span>' : ''),
                    'amount' => Money::rupiah($row->amount),
                    'outstanding' => Money::rupiah($row->outstanding_amount),
                    'status' => view('components.status-badge', ['status' => $row->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'extra' => $row->status->isOutstanding()
                            ? [[
                                'url' => route('finance.receipts.create', ['customer' => $row->customer_id]),
                                'label' => 'Terima Pembayaran',
                                'icon' => 'wallet',
                            ]]
                            : [],
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function aging(Request $request): View
    {
        $groups = $this->aging->receivables(
            customerId: $request->integer('customer') ?: null,
        );

        return view('finance.receivable.aging', [
            'buckets' => $this->aging->buckets(),
            'groups' => $groups,
            'totals' => $this->aging->totals($groups),
            'customers' => Customer::active()->orderBy('name')->pluck('name', 'id'),
            'customerId' => $request->integer('customer') ?: null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            ReceivableStatus::Open->value => ReceivableStatus::Open->label(),
            ReceivableStatus::PartiallyPaid->value => ReceivableStatus::PartiallyPaid->label(),
            ReceivableStatus::Overdue->value => ReceivableStatus::Overdue->label(),
            ReceivableStatus::Paid->value => ReceivableStatus::Paid->label(),
            ReceivableStatus::WrittenOff->value => ReceivableStatus::WrittenOff->label(),
        ];
    }
}
