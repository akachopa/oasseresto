<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Models\User;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Core\Support\Money;
use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\Payable;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Inventory\Queries\StockQuery;
use App\Modules\Notification\Models\Alert;
use App\Modules\Notification\Services\AlertEngine;
use App\Modules\Reporting\Models\DailyBusinessMetric;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Menyusun KPI owner dari daily_business_metrics plus Attention Needed dari
 * alert engine, dan rincian drill-down saat kartu KPI diklik (PLAN 12).
 */
class OwnerDashboardService
{
    public function __construct(
        private readonly MetricsAggregationService $metrics,
        private readonly AlertEngine $alerts,
        private readonly ScopeManager $scope,
        private readonly StockQuery $stock,
    ) {}

    /**
     * @return array{
     *     today: DailyBusinessMetric,
     *     yesterday: DailyBusinessMetric,
     *     month: DailyBusinessMetric,
     *     alerts: Collection<int, Alert>,
     *     trend: array<int, array{date: string, sales_amount: float, gross_profit: float}>,
     *     show_financial: bool
     * }
     */
    public function build(?User $user = null): array
    {
        $user ??= Auth::user();
        $companyId = (int) $user->company_id;
        $branchIds = $this->scope->branchIds();
        $today = now()->startOfDay();

        $this->metrics->ensure($companyId, $today, $branchIds);
        $this->alerts->scan($companyId);

        return [
            'today' => $this->metrics->read($companyId, $today, $branchIds),
            'yesterday' => $this->metrics->read($companyId, $today->copy()->subDay(), $branchIds),
            'month' => $this->metrics->period($companyId, $today->copy()->startOfMonth(), $today, $branchIds),
            'alerts' => Alert::query()
                ->unresolved()
                ->orderByRaw("case severity when 'critical' then 1 when 'warning' then 2 else 3 end")
                ->orderByDesc('count')
                ->limit(12)
                ->get(),
            'trend' => $this->metrics->trend($companyId, $today->copy()->subDays(13), $today, $branchIds),
            'show_financial' => $user->can('dashboard.financial'),
        ];
    }

    /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string>>, empty: string}
     */
    public function drill(string $focus): array
    {
        return match ($focus) {
            'sales' => $this->salesDrill(now()->toDateString(), now()->toDateString(), 'Invoice hari ini'),
            'month' => $this->salesDrill(now()->startOfMonth()->toDateString(), now()->toDateString(), 'Invoice bulan ini'),
            'profit' => $this->salesDrill(now()->startOfMonth()->toDateString(), now()->toDateString(), 'Laba kotor bulan ini', withProfit: true),
            'cash' => $this->cashDrill(),
            'ar' => $this->receivableDrill(false, 'Piutang berjalan'),
            'overdue' => $this->receivableDrill(true, 'Piutang jatuh tempo'),
            'ap' => $this->payableDrill(),
            'stock' => $this->stockDrill(),
            default => [
                'title' => 'Rincian',
                'columns' => [],
                'rows' => [],
                'empty' => 'Pilih kartu KPI untuk melihat rinciannya.',
            ],
        };
    }

    /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string>>, empty: string}
     */
    private function salesDrill(string $from, string $to, string $title, bool $withProfit = false): array
    {
        $invoices = SalesInvoice::query()
            ->with('customer')
            ->where('status', DocumentStatus::Posted)
            ->whereDate('invoice_date', '>=', $from)
            ->whereDate('invoice_date', '<=', $to)
            ->orderByDesc('invoice_date')
            ->limit(20)
            ->get();

        $columns = $withProfit
            ? ['Nomor', 'Customer', 'Nilai', 'Laba', 'Margin']
            : ['Nomor', 'Tanggal', 'Customer', 'Nilai'];

        $rows = $invoices->map(function (SalesInvoice $invoice) use ($withProfit): array {
            $url = Route::has('sales.invoices.detail')
                ? route('sales.invoices.detail', $invoice)
                : null;
            $number = $url
                ? '<a href="'.$url.'" wire:navigate class="font-mono text-xs text-brand-600">'.e($invoice->number).'</a>'
                : e($invoice->number);

            if ($withProfit) {
                return [
                    $number,
                    e((string) $invoice->customer?->name),
                    Money::rupiah($invoice->total),
                    Money::rupiah($invoice->grossProfit()),
                    Money::percent($invoice->marginPercent()),
                ];
            }

            return [
                $number,
                $invoice->invoice_date->format('d/m/Y'),
                e((string) $invoice->customer?->name),
                Money::rupiah($invoice->total),
            ];
        })->all();

        return [
            'title' => $title,
            'columns' => $columns,
            'rows' => $rows,
            'empty' => 'Belum ada invoice pada periode ini.',
        ];
    }

    /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string>>, empty: string}
     */
    private function cashDrill(): array
    {
        $accounts = CashAccount::query()->active()->orderBy('name')->get();

        return [
            'title' => 'Saldo kas & bank',
            'columns' => ['Akun', 'Jenis', 'Saldo'],
            'rows' => $accounts->map(function (CashAccount $account): array {
                $url = Route::has('finance.cash.detail')
                    ? route('finance.cash.detail', $account)
                    : null;
                $name = $url
                    ? '<a href="'.$url.'" wire:navigate class="text-brand-600">'.e($account->name).'</a>'
                    : e($account->name);

                return [$name, e($account->typeLabel()), Money::rupiah($account->balance)];
            })->all(),
            'empty' => 'Belum ada akun kas.',
        ];
    }

    /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string>>, empty: string}
     */
    private function receivableDrill(bool $overdueOnly, string $title): array
    {
        $rows = Receivable::query()
            ->with('customer')
            ->outstanding()
            ->when($overdueOnly, fn ($q) => $q->whereDate('due_date', '<', now()->toDateString()))
            ->orderBy('due_date')
            ->limit(20)
            ->get()
            ->map(fn (Receivable $row) => [
                e((string) $row->document_number),
                e((string) $row->customer?->name),
                $row->due_date->format('d/m/Y'),
                Money::rupiah($row->outstanding_amount),
            ])
            ->all();

        return [
            'title' => $title,
            'columns' => ['Dokumen', 'Customer', 'Jatuh Tempo', 'Sisa'],
            'rows' => $rows,
            'empty' => 'Tidak ada piutang pada filter ini.',
        ];
    }

    /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string>>, empty: string}
     */
    private function payableDrill(): array
    {
        $rows = Payable::query()
            ->with('supplier')
            ->outstanding()
            ->orderBy('due_date')
            ->limit(20)
            ->get()
            ->map(fn (Payable $row) => [
                e((string) $row->document_number),
                e((string) $row->supplier?->name),
                $row->due_date->format('d/m/Y'),
                Money::rupiah($row->outstanding_amount),
            ])
            ->all();

        return [
            'title' => 'Hutang berjalan',
            'columns' => ['Dokumen', 'Supplier', 'Jatuh Tempo', 'Sisa'],
            'rows' => $rows,
            'empty' => 'Tidak ada hutang berjalan.',
        ];
    }

    /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string>>, empty: string}
     */
    private function stockDrill(): array
    {
        $rows = $this->stock->belowReorderPoint()
            ->orderBy('products.name')
            ->limit(20)
            ->get()
            ->map(function ($row): array {
                $url = Route::has('inventory.stock.detail')
                    ? route('inventory.stock.detail', $row->product_id)
                    : null;
                $name = $url
                    ? '<a href="'.$url.'" wire:navigate class="text-brand-600">'.e((string) $row->product_name).'</a>'
                    : e((string) $row->product_name);

                return [
                    e((string) $row->sku),
                    $name,
                    e((string) $row->warehouse_name),
                    Money::quantity((float) $row->quantity - (float) $row->reserved_quantity),
                    Money::quantity((float) $row->reorder_point),
                ];
            })
            ->all();

        return [
            'title' => 'Stok di bawah titik reorder',
            'columns' => ['SKU', 'Produk', 'Gudang', 'Tersedia', 'Reorder'],
            'rows' => $rows,
            'empty' => 'Tidak ada SKU di bawah titik reorder.',
        ];
    }
}
