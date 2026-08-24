<?php

declare(strict_types=1);

namespace App\Modules\Notification\Services;

use App\Modules\Approval\Models\ApprovalRequest;
use App\Modules\Core\Enums\AlertSeverity;
use App\Modules\Core\Enums\AlertType;
use App\Modules\Core\Enums\ApprovalStatus;
use App\Modules\Core\Enums\DeliveryStatus;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Customer\Models\Customer;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Finance\Models\CashAccount;
use App\Modules\Finance\Models\Payable;
use App\Modules\Finance\Models\Receivable;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Notification\Models\Alert;
use App\Modules\Purchase\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Mesin alert operasional (PLAN 45). Setiap jenis temuan punya fingerprint
 * tetap per company, jadi pemindaian ulang memperbarui hitungan alih-alih
 * menumpuk baris baru, dan temuan yang sudah hilang ditandai selesai.
 */
class AlertEngine
{
    public function __construct(private readonly ScopeManager $scope) {}

    /**
     * @return array<int, Alert>
     */
    public function scan(int $companyId): array
    {
        return $this->scope->forCompany($companyId, function () use ($companyId): array {
            $open = $this->detect($companyId);
            $fingerprints = array_keys($open);

            foreach ($open as $fingerprint => $payload) {
                Alert::query()->updateOrCreate(
                    ['company_id' => $companyId, 'fingerprint' => $fingerprint],
                    $payload + [
                        'is_resolved' => false,
                        'resolved_at' => null,
                        'last_seen_at' => now(),
                    ],
                );
            }

            Alert::query()
                ->where('company_id', $companyId)
                ->where('is_resolved', false)
                ->when(
                    $fingerprints === [],
                    fn ($q) => $q,
                    fn ($q) => $q->whereNotIn('fingerprint', $fingerprints),
                )
                ->update([
                    'is_resolved' => true,
                    'resolved_at' => now(),
                ]);

            return Alert::query()
                ->unresolved()
                ->where('company_id', $companyId)
                ->orderByRaw("case severity when 'critical' then 1 when 'warning' then 2 else 3 end")
                ->orderByDesc('count')
                ->get()
                ->all();
        });
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function detect(int $companyId): array
    {
        $alerts = [];
        $today = now()->toDateString();
        $nearExpiryDays = (int) config('oasse.inventory.near_expiry_days', 60);
        $cashLow = (float) config('oasse.alerts.cash_low', 1_000_000);

        $belowReorder = (int) DB::table('stock_balances')
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->where('stock_balances.company_id', $companyId)
            ->whereNull('products.deleted_at')
            ->where('products.is_stocked', true)
            ->where('products.reorder_point', '>', 0)
            ->whereRaw('stock_balances.quantity - stock_balances.reserved_quantity <= products.reorder_point')
            ->count();

        $this->pushSummary($alerts, AlertType::StockBelowReorder, $belowReorder, $this->routeIf('inventory.stock.index'));

        $expired = Batch::query()
            ->available()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', $today)
            ->count();

        $this->pushSummary($alerts, AlertType::StockExpired, $expired, $this->routeIf('inventory.batches.index'));

        $nearExpiry = Batch::query()
            ->available()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $today)
            ->whereDate('expiry_date', '<=', now()->addDays($nearExpiryDays)->toDateString())
            ->count();

        $this->pushSummary($alerts, AlertType::StockNearExpiry, $nearExpiry, $this->routeIf('inventory.batches.index'));

        $arOverdue = Receivable::query()
            ->outstanding()
            ->whereDate('due_date', '<', $today)
            ->count();

        $this->pushSummary($alerts, AlertType::ArOverdue, $arOverdue, $this->routeIf('finance.receivables.index'));

        $apOverdue = Payable::query()
            ->outstanding()
            ->whereDate('due_date', '<', $today)
            ->count();

        $this->pushSummary($alerts, AlertType::ApOverdue, $apOverdue, $this->routeIf('finance.payables.index'));

        $pending = ApprovalRequest::query()->where('status', ApprovalStatus::Pending)->count();
        $this->pushSummary($alerts, AlertType::PendingApproval, $pending, $this->routeIf('tasks.index'));

        $picking = Delivery::query()
            ->whereIn('status', [DeliveryStatus::Ready, DeliveryStatus::Picking])
            ->count();
        $this->pushSummary($alerts, AlertType::PickingBacklog, $picking, $this->routeIf('delivery.picking.index'));

        $latePo = PurchaseOrder::query()
            ->whereIn('status', [DocumentStatus::Approved, DocumentStatus::PartiallyProcessed])
            ->whereNotNull('expected_date')
            ->whereDate('expected_date', '<', $today)
            ->count();
        $this->pushSummary($alerts, AlertType::OpenPoOverdue, $latePo, $this->routeIf('purchase.orders.index'));

        foreach (CashAccount::query()->where('is_active', true)->get() as $account) {
            if ($account->balance < 0) {
                $alerts['negative_cash:'.$account->id] = $this->payload(
                    AlertType::NegativeCash,
                    AlertSeverity::Critical,
                    'Saldo '.$account->name.' negatif',
                    'Saldo saat ini '.$this->money($account->balance).'.',
                    1,
                    $this->routeIf('finance.cash.detail', $account),
                    'cash_account',
                    (int) $account->id,
                    $account->branch_id,
                );
            } elseif ($account->balance < $cashLow) {
                $alerts['cash_low:'.$account->id] = $this->payload(
                    AlertType::CashLow,
                    AlertSeverity::Warning,
                    'Saldo '.$account->name.' menipis',
                    'Saldo saat ini '.$this->money($account->balance).', ambang '.$this->money($cashLow).'.',
                    1,
                    $this->routeIf('finance.cash.detail', $account),
                    'cash_account',
                    (int) $account->id,
                    $account->branch_id,
                );
            }
        }

        $overLimit = Customer::query()
            ->where('is_active', true)
            ->where('credit_limit', '>', 0)
            ->whereColumn('outstanding_amount', '>', 'credit_limit')
            ->get();

        foreach ($overLimit as $customer) {
            $alerts['credit_limit:'.$customer->id] = $this->payload(
                AlertType::CreditLimit,
                AlertSeverity::Critical,
                $customer->name.' melebihi credit limit',
                'Piutang '.$this->money($customer->outstanding_amount)
                    .' dari limit '.$this->money($customer->credit_limit).'.',
                1,
                $this->routeIf('customers.detail', $customer),
                'customer',
                (int) $customer->id,
                $customer->branch_id,
            );
        }

        return $alerts;
    }

    /**
     * @param  array<string, array<string, mixed>>  $alerts
     */
    private function pushSummary(array &$alerts, AlertType $type, int $count, ?string $url): void
    {
        if ($count <= 0) {
            return;
        }

        $alerts[$type->value] = $this->payload(
            $type,
            $type->defaultSeverity(),
            $type->label(),
            $count.' item perlu ditindaklanjuti.',
            $count,
            $url,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        AlertType $type,
        AlertSeverity $severity,
        string $title,
        string $message,
        int $count,
        ?string $url,
        ?string $entityType = null,
        ?int $entityId = null,
        mixed $branchId = null,
    ): array {
        return [
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'count' => $count,
            'url' => $url,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'branch_id' => $branchId ? (int) $branchId : null,
        ];
    }

    private function money(float $value): string
    {
        return 'Rp '.number_format($value, 0, ',', '.');
    }

    private function routeIf(string $name, mixed $parameters = []): ?string
    {
        return Route::has($name) ? route($name, $parameters) : null;
    }
}
