<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Company\Models\Company;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Notification\Services\AlertEngine;
use Illuminate\Console\Command;

class ScanAlertsCommand extends Command
{
    protected $signature = 'oasse:scan-alerts {--company=}';

    protected $description = 'Pindai kondisi operasional dan perbarui Attention Needed';

    public function handle(AlertEngine $engine, ScopeManager $scope): int
    {
        $companyId = $this->option('company') ? (int) $this->option('company') : null;

        $companies = $scope->withoutRestriction(fn () => $companyId
            ? Company::query()->whereKey($companyId)->get()
            : Company::query()->where('is_active', true)->get());

        foreach ($companies as $company) {
            $alerts = $engine->scan((int) $company->id);
            $this->info($company->code.': '.count($alerts).' alert terbuka.');
        }

        return self::SUCCESS;
    }
}
