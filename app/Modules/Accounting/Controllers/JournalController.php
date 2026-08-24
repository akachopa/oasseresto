<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Controllers;

use App\Modules\Accounting\DTO\JournalDraft;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountingPostingService;
use App\Modules\Core\Enums\JournalStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class JournalController
{
    public function __construct(
        private readonly AccountingPostingService $posting,
        private readonly LocationScope $location,
        private readonly ScopeManager $scope,
    ) {}

    public function index(): View
    {
        return view('accounting.journal.index', [
            'statuses' => collect(JournalStatus::cases())->mapWithKeys(fn (JournalStatus $status) => [$status->value => $status->label()]),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = JournalEntry::query()->with('period');

        $this->location->applyBranch($query, 'journal_entries.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['number', 'description', 'document_number'])
                ->orderable([
                    null, 'number', 'entry_date', 'description', 'document_type',
                    'total_debit', 'status', null,
                ])
                ->filter('status', fn ($q, $value) => $q->where('status', $value))
                ->filter('source', fn ($q, $value) => $q->where('source', $value))
                ->transform(fn (JournalEntry $entry) => [
                    'number' => '<a class="font-mono text-xs hover:text-brand-600" href="'
                        .route('accounting.journals.detail', $entry).'">'.e($entry->number).'</a>',
                    'date' => $entry->entry_date->format('d/m/Y'),
                    'description' => e($entry->description),
                    'document' => $entry->document_number
                        ? '<span class="font-mono text-xs">'.e($entry->document_number).'</span>'
                        : '<span class="text-muted">'.e((string) $entry->document_type).'</span>',
                    'amount' => Money::rupiah($entry->total_debit),
                    'status' => view('components.status-badge', ['status' => $entry->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'detail' => route('accounting.journals.detail', $entry),
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(): View
    {
        return view('accounting.journal.create', [
            'accounts' => Account::postable()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ]);

        $company = $this->scope->company();

        try {
            $entry = $this->posting->post(new JournalDraft(
                company: $company,
                documentType: 'manual',
                documentId: 0,
                purpose: 'manual:'.Str::uuid()->toString(),
                entryDate: Carbon::parse($validated['entry_date']),
                lines: $validated['lines'],
                description: $validated['description'],
                note: $validated['note'] ?? null,
                branchId: $this->scope->branchId(),
                createdBy: $request->user()->id,
                source: 'manual',
            ));
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('accounting.journals.detail', $entry)
            ->with('status', 'Jurnal manual diposting.');
    }

    public function detail(JournalEntry $journal): View
    {
        $journal->load(['lines.account', 'period', 'creator', 'reversalOf']);

        return view('accounting.journal.detail', ['entry' => $journal]);
    }

    public function reverse(Request $request, JournalEntry $journal): RedirectResponse
    {
        try {
            $this->posting->reverse($journal, $request->user()->id, $request->input('reason'));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Jurnal dibalik. Koreksi tercatat sebagai jurnal reversal.');
    }
}
