<?php

declare(strict_types=1);

namespace App\Modules\Core\Jobs;

use App\Modules\Core\Models\ImportJob;
use App\Modules\Core\Services\ImportService;
use App\Modules\Core\Services\ScopeManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessImportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $importJobId) {}

    public function handle(ImportService $imports, ScopeManager $scope): void
    {
        $job = ImportJob::withoutGlobalScopes()->find($this->importJobId);

        if ($job === null) {
            return;
        }

        $scope->forCompany((int) $job->company_id, function () use ($imports, $job): void {
            $imports->process($job);
        });
    }
}
