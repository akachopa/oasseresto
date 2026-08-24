<?php

declare(strict_types=1);

use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Reporting\Models\DailyBusinessMetric;
use App\Modules\Reporting\Services\MetricsAggregationService;

it('menyimpan omzet dan piutang harian ke daily_business_metrics', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();

    ['invoice' => $invoice, 'receivable' => $receivable] =
        buildReceivable($company->id, $warehouse->id, $branch->id, 20_000, 20);

    expect($invoice->status)->toBe(DocumentStatus::Posted);

    app(MetricsAggregationService::class)->aggregateCompany($company->id, now());

    $metric = DailyBusinessMetric::query()
        ->whereNull('branch_id')
        ->whereDate('metric_date', now()->toDateString())
        ->firstOrFail();

    expect($metric->sales_amount)->toBe((float) $invoice->total);
    expect($metric->sales_count)->toBe(1);
    expect($metric->ar_outstanding)->toBe((float) $receivable->outstanding_amount);
    expect($metric->gross_profit)->toBe((float) $invoice->grossProfit());

    $branchRow = DailyBusinessMetric::query()
        ->where('branch_id', $branch->id)
        ->whereDate('metric_date', now()->toDateString())
        ->firstOrFail();

    expect($branchRow->sales_amount)->toBe((float) $invoice->total);
});

it('mengisi ulang baris yang sama saat agregasi dijalankan ulang', function (): void {
    ['company' => $company, 'branch' => $branch, 'warehouse' => $warehouse] = $this->bootCompany();

    buildReceivable($company->id, $warehouse->id, $branch->id);

    $service = app(MetricsAggregationService::class);
    $service->aggregateCompany($company->id);
    $service->aggregateCompany($company->id);

    expect(
        DailyBusinessMetric::query()
            ->whereNull('branch_id')
            ->whereDate('metric_date', now()->toDateString())
            ->count()
    )->toBe(1);
});
