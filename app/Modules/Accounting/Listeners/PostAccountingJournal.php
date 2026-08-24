<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Listeners;

use App\Modules\Accounting\DTO\JournalDraft;
use App\Modules\Accounting\Events\BusinessDocumentPosted;
use App\Modules\Accounting\Exceptions\UnbalancedJournalException;
use App\Modules\Accounting\Services\AccountingPostingService;
use App\Modules\Accounting\Services\DocumentPostingMapper;
use Illuminate\Support\Facades\Auth;

class PostAccountingJournal
{
    public function __construct(
        private readonly DocumentPostingMapper $mapper,
        private readonly AccountingPostingService $posting,
    ) {}

    public function handle(BusinessDocumentPosted $event): void
    {
        if (! config('oasse.accounting.auto_post', true)) {
            return;
        }

        if ($event->isReverse()) {
            $this->posting->reverseDocument(
                $event->documentType,
                (int) $event->document->getKey(),
                Auth::id(),
                'Pembalikan dokumen',
            );

            return;
        }

        foreach ($this->mapper->draftsFor($event->documentType, $event->document) as $draft) {
            if (! $draft instanceof JournalDraft || $draft->lines === []) {
                continue;
            }

            try {
                $this->posting->post($draft);
            } catch (UnbalancedJournalException $exception) {
                if (str_contains($exception->getMessage(), 'kosong')) {
                    continue;
                }

                throw $exception;
            }
        }
    }
}
