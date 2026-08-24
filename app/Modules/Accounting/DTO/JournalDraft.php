<?php

declare(strict_types=1);

namespace App\Modules\Accounting\DTO;

use App\Modules\Company\Models\Company;
use Carbon\CarbonInterface;

class JournalDraft
{
    /**
     * @param  list<array{account_id:int,debit?:float|int|string,credit?:float|int|string,memo?:?string,cost_center_id?:?int,partner_type?:?string,partner_id?:?int,branch_id?:?int}>  $lines
     */
    public function __construct(
        public Company $company,
        public string $documentType,
        public int $documentId,
        public string $purpose,
        public CarbonInterface $entryDate,
        public array $lines,
        public string $description = '',
        public ?string $documentNumber = null,
        public ?string $note = null,
        public ?int $branchId = null,
        public ?int $createdBy = null,
        public string $source = 'system',
    ) {}
}
