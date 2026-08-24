<?php

declare(strict_types=1);

namespace App\Modules\Purchase\Controllers;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Services\ScopeManager;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Purchase\Services\PurchaseRequestService;
use App\Modules\Purchase\Services\ReorderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Rekomendasi reorder hanya menyarankan; keputusan tetap lewat purchase
 * request supaya jalur approval pembelian tidak bisa dilewati (PLAN 18).
 */
class ReorderController
{
    public function __construct(
        private readonly ReorderService $reorder,
        private readonly PurchaseRequestService $requests,
        private readonly ScopeManager $scope,
    ) {}

    public function index(): View
    {
        return view('purchase.reorder.index', [
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $warehouseId = $request->input('filters.warehouse');

        $query = $this->reorder->query(
            $warehouseId ? (int) $warehouseId : null,
            $this->scope->branchId(),
        );

        return response()->json(
            ServerTable::of($query)
                ->searchable(['sku', 'name', 'supplier_name'])
                ->orderable([
                    null, 'sku', 'name', 'on_hand', 'incoming',
                    'projected_quantity', 'reorder_point', 'supplier_name', null, null,
                ])
                ->transform(function (object $row): array {
                    $row = $this->reorder->decorate($row);

                    return [
                        'sku' => '<span class="font-mono text-xs">'.e($row->sku).'</span>',
                        'name' => e($row->name),
                        'on_hand' => Money::quantity($row->on_hand).' '.e((string) $row->unit_code),
                        'incoming' => Money::quantity($row->incoming),
                        'available' => ($row->is_critical ? '<span class="badge-danger">' : '<span>')
                            .Money::quantity($row->available).'</span>',
                        'reorder_point' => Money::quantity($row->reorder_point),
                        'supplier' => e((string) ($row->supplier_name ?? '-')),
                        'suggestion' => Money::quantity($row->suggested_quantity)
                            .'<span class="text-muted block text-xs">'.Money::compact($row->estimated_cost).'</span>',
                        'aksi' => view('components.row-actions', [
                            'detail' => route('inventory.stock.detail', $row->id),
                        ])->render(),
                    ];
                })
                ->make($request),
        );
    }

    /**
     * Seluruh rekomendasi gudang terpilih dituangkan ke satu PR draft supaya
     * pembelian bisa memangkas atau menambah baris sebelum diajukan.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ]);

        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);
        $suggestions = $this->reorder->suggestions($warehouse->getKey(), $warehouse->branch_id);

        if ($suggestions->isEmpty()) {
            return back()->with('error', 'Tidak ada barang yang perlu dipesan untuk gudang ini.');
        }

        $items = $suggestions
            ->map(fn (object $row) => [
                'product_id' => (int) $row->id,
                'unit_id' => (int) $row->base_unit_id,
                'quantity' => $row->suggested_quantity,
                'estimated_price' => (float) $row->last_purchase_cost,
                'suggested_supplier_id' => $row->supplier_id ? (int) $row->supplier_id : null,
            ])
            ->all();

        try {
            $purchaseRequest = $this->requests->save(null, [
                'warehouse_id' => $warehouse->getKey(),
                'request_date' => now()->toDateString(),
                'priority' => 'normal',
                'note' => 'Dibuat dari rekomendasi reorder',
            ], $items);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('purchase.requests.edit', $purchaseRequest)
            ->with('status', 'Purchase request draft dibuat dari '.count($items).' rekomendasi.');
    }
}
