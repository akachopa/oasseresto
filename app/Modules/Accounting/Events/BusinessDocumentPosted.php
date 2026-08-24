<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Events;

class BusinessDocumentPosted
{
    public function __construct(
        public readonly string $documentType,
        public readonly mixed $document,
        public readonly string $action = 'post',
    ) {}

    public function isReverse(): bool
    {
        return $this->action === 'reverse';
    }
}
