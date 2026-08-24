<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\DTO\JournalDraft;
use App\Modules\Accounting\Exceptions\UnbalancedJournalException;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Core\Enums\JournalStatus as CoreJournalStatus;
use App\Modules\Core\Services\DocumentNumberService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AccountingPostingService
{
    public function __construct(
        protected AccountingPeriodService $periods,
        protected DocumentNumberService $numbers,
    ) {}

    public function post(JournalDraft $draft): JournalEntry
    {
        $existing = $this->existing($draft);

        if ($existing !== null) {
            return $existing;
        }

        $lines = $this->normalizeLines($draft->lines);

        if ($lines === []) {
            throw new UnbalancedJournalException('Jurnal tidak boleh kosong.');
        }

        [$debit, $credit] = $this->totals($lines);

        if (abs($debit - $credit) >= 0.0001) {
            throw new UnbalancedJournalException(
                'Jurnal tidak seimbang: debit '.$debit.' vs kredit '.$credit.'.'
            );
        }

        try {
            return DB::transaction(function () use ($draft, $lines, $debit, $credit): JournalEntry {
                $period = $this->periods->ensureOpen($draft->company, $draft->entryDate);
                $date = Carbon::parse($draft->entryDate);

                $entry = JournalEntry::query()->create([
                    'company_id' => $draft->company->id,
                    'branch_id' => $draft->branchId,
                    'accounting_period_id' => $period->id,
                    'number' => $this->numbers->next(
                        'journal_entry',
                        $draft->branchId,
                        $date,
                        (int) $draft->company->id,
                    ),
                    'entry_date' => $date->toDateString(),
                    'status' => CoreJournalStatus::Posted,
                    'source' => $draft->source,
                    'document_type' => $draft->documentType,
                    'document_id' => $draft->documentId,
                    'document_number' => $draft->documentNumber,
                    'purpose' => $draft->purpose,
                    'description' => $draft->description,
                    'note' => $draft->note,
                    'total_debit' => $debit,
                    'total_credit' => $credit,
                    'created_by' => $draft->createdBy,
                    'posted_at' => now(),
                ]);

                foreach ($lines as $i => $line) {
                    JournalLine::query()->create([
                        'company_id' => $draft->company->id,
                        'journal_entry_id' => $entry->id,
                        'account_id' => $line['account_id'],
                        'branch_id' => $line['branch_id'] ?? $draft->branchId,
                        'cost_center_id' => $line['cost_center_id'] ?? null,
                        'sequence' => $i + 1,
                        'debit' => $line['debit'],
                        'credit' => $line['credit'],
                        'memo' => $line['memo'] ?? $draft->description,
                        'partner_type' => $line['partner_type'] ?? null,
                        'partner_id' => $line['partner_id'] ?? null,
                    ]);
                }

                return $entry->fresh(['lines']);
            });
        } catch (UniqueConstraintViolationException) {
            return $this->existing($draft) ?? throw new UnbalancedJournalException('Jurnal duplikat tidak bisa dibaca ulang.');
        }
    }

    public function reverse(JournalEntry $entry, ?int $userId = null, ?string $reason = null): JournalEntry
    {
        if ($entry->status === CoreJournalStatus::Reversed && $entry->reversal_of_id === null) {
            $existing = JournalEntry::query()->where('reversal_of_id', $entry->id)->first();

            if ($existing) {
                return $existing;
            }
        }

        $existing = JournalEntry::query()->where('reversal_of_id', $entry->id)->first();

        if ($existing) {
            return $existing;
        }

        $entry->loadMissing('lines', 'company');

        $draft = new JournalDraft(
            company: $entry->company,
            documentType: (string) $entry->document_type,
            documentId: (int) $entry->document_id,
            purpose: $entry->purpose.':reversal',
            entryDate: now(),
            lines: $entry->lines->map(fn (JournalLine $line) => [
                'account_id' => $line->account_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'memo' => 'Reversal: '.($line->memo ?: $entry->number),
                'cost_center_id' => $line->cost_center_id,
                'partner_type' => $line->partner_type,
                'partner_id' => $line->partner_id,
                'branch_id' => $line->branch_id,
            ])->all(),
            description: $reason ?: 'Reversal '.$entry->number,
            documentNumber: $entry->document_number,
            branchId: $entry->branch_id,
            createdBy: $userId,
        );

        $reversal = $this->post($draft);
        $reversal->update(['reversal_of_id' => $entry->id]);
        $entry->update([
            'status' => CoreJournalStatus::Reversed,
            'reversed_at' => now(),
        ]);

        return $reversal->fresh(['lines']);
    }

    public function reverseDocument(string $documentType, int $documentId, ?int $userId = null, ?string $reason = null): void
    {
        JournalEntry::query()
            ->where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->where('status', CoreJournalStatus::Posted)
            ->whereNull('reversal_of_id')
            ->get()
            ->each(fn (JournalEntry $entry) => $this->reverse($entry, $userId, $reason));
    }

    private function existing(JournalDraft $draft): ?JournalEntry
    {
        return JournalEntry::query()
            ->where('company_id', $draft->company->id)
            ->where('document_type', $draft->documentType)
            ->where('document_id', $draft->documentId)
            ->where('purpose', $draft->purpose)
            ->first();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{account_id:int,debit:float,credit:float,memo:?string,cost_center_id:?int,partner_type:?string,partner_id:?int,branch_id:?int}>
     */
    private function normalizeLines(array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            $debit = round((float) ($line['debit'] ?? 0), 4);
            $credit = round((float) ($line['credit'] ?? 0), 4);

            if ($debit < 0.0001 && $credit < 0.0001) {
                continue;
            }

            if ($debit > 0 && $credit > 0) {
                throw new UnbalancedJournalException('Satu baris jurnal tidak boleh debit dan kredit sekaligus.');
            }

            $normalized[] = [
                'account_id' => (int) $line['account_id'],
                'debit' => $debit,
                'credit' => $credit,
                'memo' => $line['memo'] ?? null,
                'cost_center_id' => $line['cost_center_id'] ?? null,
                'partner_type' => $line['partner_type'] ?? null,
                'partner_id' => isset($line['partner_id']) ? (int) $line['partner_id'] : null,
                'branch_id' => $line['branch_id'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{debit:float,credit:float}>  $lines
     * @return array{0:float,1:float}
     */
    private function totals(array $lines): array
    {
        $debit = 0.0;
        $credit = 0.0;

        foreach ($lines as $line) {
            $debit += $line['debit'];
            $credit += $line['credit'];
        }

        return [round($debit, 4), round($credit, 4)];
    }
}
