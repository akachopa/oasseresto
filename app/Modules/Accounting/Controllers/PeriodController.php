<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Controllers;

use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Services\AccountingPeriodService;
use App\Modules\Core\Enums\AccountingPeriodStatus;
use App\Modules\Core\Services\ScopeManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use RuntimeException;

class PeriodController
{
    public function __construct(
        private readonly AccountingPeriodService $periods,
        private readonly ScopeManager $scope,
    ) {}

    public function index(): View
    {
        $company = $this->scope->company();
        $year = (int) request('year', now()->year);

        for ($month = 1; $month <= 12; $month++) {
            $this->periods->findOrCreate($company, Carbon::create($year, $month, 1));
        }

        $rows = AccountingPeriod::query()
            ->where('year', $year)
            ->orderBy('month')
            ->get();

        return view('accounting.period.index', [
            'year' => $year,
            'periods' => $rows,
            'statuses' => collect(AccountingPeriodStatus::cases())->mapWithKeys(
                fn (AccountingPeriodStatus $status) => [$status->value => $status->label()]
            ),
        ]);
    }

    public function close(Request $request, AccountingPeriod $period): RedirectResponse
    {
        try {
            $this->periods->close($period, $request->user()->id, $request->input('note'));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Periode '.$period->label().' ditutup.');
    }

    public function reopen(Request $request, AccountingPeriod $period): RedirectResponse
    {
        try {
            $this->periods->reopen($period, $request->user()->id, $request->input('note'));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Periode '.$period->label().' dibuka kembali.');
    }
}
