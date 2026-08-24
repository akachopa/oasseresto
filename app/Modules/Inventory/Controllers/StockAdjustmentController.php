<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Services\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class StockAdjustmentController
{
    public function __construct(
        private readonly StockAdjustmentService $adjustments,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        return view('inventory.adjustment.index', [
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'reasons' => StockAdjustment::reasons(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = StockAdjustment::query()
            ->join('warehouses', 'warehouses.id', '=', 'stock_adjustments.warehouse_id')
            ->select(['stock_adjustments.*', 'warehouses.name as warehouse_name']);

        $this->location->applyBranch($query, 'stock_adjustments.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['stock_adjustments.number', 'stock_adjustments.note', 'warehouses.name'])
                ->orderable([
                    null, 'stock_adjustments.number', 'stock_adjustments.adjustment_date',
                    'warehouses.name', 'stock_adjustments.reason', 'stock_adjustments.total_value',
                    'stock_adjustments.status', null,
                ])
                ->filter('warehouse', fn ($q, $value) => $q->where('stock_adjustments.warehouse_id', $value))
                ->filter('reason', fn ($q, $value) => $q->where('stock_adjustments.reason', $value))
                ->filter('status', fn ($q, $value) => $q->where('stock_adjustments.status', $value))
                ->transform(fn (StockAdjustment $adjustment) => [
                    'number' => '<a class="font-mono text-xs hover:text-brand-600" href="'
                        .route('inventory.adjustments.detail', $adjustment).'">'.e($adjustment->number).'</a>',
                    'date' => $adjustment->adjustment_date->format('d/m/Y'),
                    'warehouse' => e((string) $adjustment->warehouse_name),
                    'reason' => e($adjustment->reasonLabel()),
                    'value' => $this->valueCell($adjustment),
                    'status' => view('components.status-badge', ['status' => $adjustment->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'detail' => route('inventory.adjustments.detail', $adjustment),
                        'edit' => $adjustment->status->isEditable() ? route('inventory.adjustments.edit', $adjustment) : null,
                        'delete' => $adjustment->status->isEditable() ? route('inventory.adjustments.hapus', $adjustment) : null,
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(): View
    {
        return view('inventory.adjustment.create');
    }

    public function edit(StockAdjustment $stockAdjustment): View
    {
        abort_unless($stockAdjustment->status->isEditable(), 403);

        return view('inventory.adjustment.edit', ['adjustment' => $stockAdjustment]);
    }

    public function detail(StockAdjustment $stockAdjustment): View
    {
        $stockAdjustment->load(['items.product', 'items.unit', 'items.batch', 'warehouse', 'creator']);

        return view('inventory.adjustment.detail', ['adjustment' => $stockAdjustment]);
    }

    public function post(StockAdjustment $stockAdjustment): RedirectResponse
    {
        try {
            $this->adjustments->post($stockAdjustment);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Penyesuaian berhasil diposting.');
    }

    public function hapus(StockAdjustment $stockAdjustment): RedirectResponse
    {
        try {
            $this->adjustments->cancel($stockAdjustment);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Penyesuaian dibatalkan.');
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            DocumentStatus::Draft->value => DocumentStatus::Draft->label(),
            DocumentStatus::Posted->value => DocumentStatus::Posted->label(),
            DocumentStatus::Cancelled->value => DocumentStatus::Cancelled->label(),
        ];
    }

    private function valueCell(StockAdjustment $adjustment): string
    {
        $class = $adjustment->total_value < 0 ? 'text-negative' : 'text-positive';

        return '<span class="'.$class.'">'.Money::rupiah($adjustment->total_value).'</span>';
    }
}
