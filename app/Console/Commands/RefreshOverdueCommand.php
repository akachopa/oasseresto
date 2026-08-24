<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Company\Models\Company;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Finance\Models\Payable;
use App\Modules\Finance\Models\Receivable;
use Illuminate\Console\Command;

class RefreshOverdueCommand extends Command
{
    protected $signature = 'oasse:refresh-overdue';

    protected $description = 'Perbarui status jatuh tempo piutang dan hutang';

    public function handle(ScopeManager $scope): int
    {
        $scope->withoutRestriction(function (): void {
            foreach (Company::query()->where('is_active', true)->get() as $company) {
                Receivable::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->outstanding()
                    ->get()
                    ->each(fn (Receivable $row) => $row->refreshStatus()->save());

                Payable::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->outstanding()
                    ->get()
                    ->each(fn (Payable $row) => $row->refreshStatus()->save());
            }
        });

        $this->info('Status jatuh tempo diperbarui.');

        return self::SUCCESS;
    }
}
