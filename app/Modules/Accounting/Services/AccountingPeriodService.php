<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Exceptions\ClosedPeriodException;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Company\Models\Company;
use App\Modules\Core\Enums\AccountingPeriodStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AccountingPeriodService
{
    public function ensureOpen(Company $company, CarbonInterface $entryDate): AccountingPeriod
    {
        $period = $this->findOrCreate($company, $entryDate);

        if (! $period->allowsPosting()) {
            throw new ClosedPeriodException(
                "Periode akuntansi {$period->label()} sudah ditutup. Koreksi harus lewat jurnal reversal."
            );
        }

        return $period;
    }

    public function findOrCreate(Company $company, CarbonInterface $entryDate): AccountingPeriod
    {
        $date = Carbon::parse($entryDate)->startOfDay();
        $year = (int) $date->year;
        $month = (int) $date->month;

        $existing = AccountingPeriod::query()
            ->where('company_id', $company->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($existing) {
            return $existing;
        }

        return AccountingPeriod::query()->create([
            'company_id' => $company->id,
            'year' => $year,
            'month' => $month,
            'start_date' => $date->copy()->startOfMonth()->toDateString(),
            'end_date' => $date->copy()->endOfMonth()->toDateString(),
            'status' => AccountingPeriodStatus::Open,
        ]);
    }

    public function close(AccountingPeriod $period, int $userId, ?string $note = null): AccountingPeriod
    {
        $period->update([
            'status' => AccountingPeriodStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $userId,
            'reopened_at' => null,
            'reopened_by' => null,
            'note' => $note ?? $period->note,
        ]);

        return $period->fresh();
    }

    public function reopen(AccountingPeriod $period, int $userId, ?string $note = null): AccountingPeriod
    {
        $period->update([
            'status' => AccountingPeriodStatus::Reopened,
            'reopened_at' => now(),
            'reopened_by' => $userId,
            'note' => $note ?? $period->note,
        ]);

        return $period->fresh();
    }

    public function closeYear(Company $company, int $year, int $userId): void
    {
        DB::transaction(function () use ($company, $year, $userId): void {
            AccountingPeriod::query()
                ->where('company_id', $company->id)
                ->where('year', $year)
                ->where('status', '!=', AccountingPeriodStatus::Closed)
                ->get()
                ->each(fn (AccountingPeriod $period) => $this->close($period, $userId));
        });
    }
}
