<?php

declare(strict_types=1);

namespace App\Modules\Finance\Controllers;

use App\Modules\Core\Enums\PayableStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Finance\Models\Payable;
use App\Modules\Finance\Services\AgingService;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayableController
{
    public function __construct(
        private readonly AgingService $aging,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        $summary = Payable::query()
            ->outstanding()
            ->selectRaw('COALESCE(SUM(outstanding_amount), 0) as total, COUNT(*) as documents')
            ->first();

        $dueSoon = Payable::query()
            ->outstanding()
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->selectRaw('COALESCE(SUM(outstanding_amount), 0) as total, COUNT(*) as documents')
            ->first();

        return view('finance.payable.index', [
            'suppliers' => Supplier::active()->orderBy('name')->pluck('name', 'id'),
            'statuses' => self::statusOptions(),
            'outstandingTotal' => (float) $summary->total,
            'outstandingCount' => (int) $summary->documents,
            'dueSoonTotal' => (float) $dueSoon->total,
            'dueSoonCount' => (int) $dueSoon->documents,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Payable::query()
            ->join('suppliers', 'suppliers.id', '=', 'payables.supplier_id')
            ->select(['payables.*', 'suppliers.name as supplier_name']);

        $this->location->applyBranch($query, 'payables.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable([
                    'payables.document_number', 'payables.supplier_document_number', 'suppliers.name',
                ])
                ->orderable([
                    null, 'payables.document_number', 'payables.invoice_date',
                    'suppliers.name', 'payables.due_date', 'payables.amount',
                    'payables.outstanding_amount', 'payables.status', null,
                ])
                ->filter('supplier', fn ($q, $value) => $q->where('payables.supplier_id', $value))
                ->filter('status', fn ($q, $value) => $q->where('payables.status', $value))
                ->filter('outstanding', fn ($q) => $q->outstanding())
                ->filter('due_soon', fn ($q) => $q
                    ->outstanding()
                    ->whereBetween('payables.due_date', [now()->toDateString(), now()->addDays(7)->toDateString()]))
                ->transform(fn (Payable $row) => [
                    'document' => '<span class="font-mono text-xs">'.e($row->document_number).'</span>'
                        .($row->supplier_document_number
                            ? '<span class="text-muted block text-xs">'.e($row->supplier_document_number).'</span>'
                            : ''),
                    'date' => $row->invoice_date->format('d/m/Y'),
                    'supplier' => e((string) $row->supplier_name),
                    'due' => $row->due_date->format('d/m/Y')
                        .($row->isOverdue() ? ' <span class="badge-danger ml-1">'.$row->daysOverdue().' hari</span>' : ''),
                    'amount' => Money::rupiah($row->amount),
                    'outstanding' => Money::rupiah($row->outstanding_amount),
                    'status' => view('components.status-badge', ['status' => $row->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'extra' => $row->outstanding_amount > 0
                            ? [[
                                'url' => route('finance.payments.create', ['supplier' => $row->supplier_id]),
                                'label' => 'Bayar',
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
        $groups = $this->aging->payables(
            supplierId: $request->integer('supplier') ?: null,
        );

        return view('finance.payable.aging', [
            'buckets' => $this->aging->buckets(),
            'groups' => $groups,
            'totals' => $this->aging->totals($groups),
            'suppliers' => Supplier::active()->orderBy('name')->pluck('name', 'id'),
            'supplierId' => $request->integer('supplier') ?: null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            PayableStatus::Open->value => PayableStatus::Open->label(),
            PayableStatus::PartiallyPaid->value => PayableStatus::PartiallyPaid->label(),
            PayableStatus::Overdue->value => PayableStatus::Overdue->label(),
            PayableStatus::Paid->value => PayableStatus::Paid->label(),
        ];
    }
}
