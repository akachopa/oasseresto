<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Company\Models\Company;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Reporting\Services\MetricsAggregationService;
use Illuminate\Console\Command;

class AggregateMetricsCommand extends Command
{
    protected $signature = 'oasse:aggregate-metrics {--date=} {--company=}';

    protected $description = 'Hitung ulang daily_business_metrics untuk dashboard owner';

    public function handle(MetricsAggregationService $metrics, ScopeManager $scope): int
    {
        $date = $this->option('date');
        $companyId = $this->option('company') ? (int) $this->option('company') : null;

        $companies = $scope->withoutRestriction(fn () => $companyId
            ? Company::query()->whereKey($companyId)->get()
            : Company::query()->where('is_active', true)->get());

        foreach ($companies as $company) {
            $metrics->aggregateCompany((int) $company->id, $date);
            $this->info('Metrik '.$company->code.' untuk '.($date ?: now()->toDateString()).' selesai.');
        }

        return self::SUCCESS;
    }
}
