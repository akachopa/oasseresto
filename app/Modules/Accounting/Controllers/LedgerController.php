<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Controllers;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\FinancialReportService;
use App\Modules\Core\Services\ScopeManager;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class LedgerController
{
    public function __construct(
        private readonly FinancialReportService $reports,
        private readonly ScopeManager $scope,
    ) {}

    public function index(Request $request): View
    {
        $accounts = Account::postable()->orderBy('code')->get();
        $account = $request->filled('account')
            ? Account::postable()->find($request->integer('account'))
            : $accounts->first();

        [$from, $to] = $this->range($request);
        $ledger = $account
            ? $this->reports->generalLedger($this->scope->company(), $account, $from, $to)
            : ['rows' => collect(), 'opening' => 0, 'closing' => 0];

        return view('accounting.ledger.index', [
            'accounts' => $accounts,
            'account' => $account,
            'from' => $from,
            'to' => $to,
            'ledger' => $ledger,
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $from = $request->date('from') ?: now()->startOfMonth();
        $to = $request->date('to') ?: now()->endOfMonth();

        return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()];
    }
}
