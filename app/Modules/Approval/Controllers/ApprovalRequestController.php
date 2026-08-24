<?php

declare(strict_types=1);

namespace App\Modules\Approval\Controllers;

use App\Modules\Approval\Controllers\ApprovalRuleController as Rules;
use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Core\Enums\ApprovalStatus;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ApprovalRequestController
{
    public function __construct(private readonly ApprovalService $approvals) {}

    public function index(): View
    {
        return view('approval.request.index', [
            'documentTypes' => Rules::documentTypes(),
            'statuses' => collect(ApprovalStatus::cases())->mapWithKeys(
                fn (ApprovalStatus $status) => [$status->value => $status->label()]
            ),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = ApprovalRequest::query()
            ->leftJoin('users', 'users.id', '=', 'approval_requests.requested_by')
            ->select(['approval_requests.*', 'users.name as requester_name']);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['approval_requests.document_number', 'approval_requests.title', 'users.name'])
                ->orderable([
                    null, 'approval_requests.requested_at', 'approval_requests.document_type',
                    'approval_requests.document_number', 'approval_requests.amount',
                    'approval_requests.status', null,
                ])
                ->filter('document_type', fn ($q, $value) => $q->where('approval_requests.document_type', $value))
                ->filter('status', fn ($q, $value) => $q->where('approval_requests.status', $value))
                ->transform(fn (ApprovalRequest $row) => [
                    'requested_at' => $row->requested_at?->format('d/m/Y H:i'),
                    'document' => e(Rules::documentTypes()[$row->document_type] ?? $row->document_type),
                    'number' => '<span class="font-mono text-xs">'.e((string) $row->document_number).'</span>',
                    'title' => e($row->title),
                    'amount' => Money::rupiah($row->amount),
                    'status' => view('components.status-badge', ['status' => $row->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'detail' => route('approval.requests.detail', $row),
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function detail(ApprovalRequest $request): View
    {
        $request->load('steps', 'requester');

        return view('approval.request.detail', [
            'request' => $request,
            'canAct' => $this->approvals->canAct($request, auth()->user()),
        ]);
    }

    public function approve(Request $http, ApprovalRequest $request): RedirectResponse
    {
        return $this->act($http, $request, true);
    }

    public function reject(Request $http, ApprovalRequest $request): RedirectResponse
    {
        return $this->act($http, $request, false);
    }

    private function act(Request $http, ApprovalRequest $request, bool $approve): RedirectResponse
    {
        $note = $http->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;

        try {
            if ($approve) {
                $this->approvals->approve($request, auth()->user(), $note);
                activity()->performedOn($request)->event('approved')->log('Approval disetujui');

                return back()->with('status', 'Dokumen disetujui.');
            }

            $this->approvals->reject($request, auth()->user(), $note ?: 'Ditolak');
            activity()->performedOn($request)->event('rejected')->log('Approval ditolak');

            return back()->with('status', 'Dokumen ditolak.');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
