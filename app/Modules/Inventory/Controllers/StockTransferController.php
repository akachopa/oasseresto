<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Services\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class StockTransferController
{
    public function __construct(
        private readonly StockTransferService $transfers,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        return view('inventory.transfer.index', [
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = StockTransfer::query()
            ->join('warehouses as source', 'source.id', '=', 'stock_transfers.from_warehouse_id')
            ->join('warehouses as target', 'target.id', '=', 'stock_transfers.to_warehouse_id')
            ->select([
                'stock_transfers.*',
                'source.name as from_name',
                'target.name as to_name',
            ]);

        $this->location->applyBranch($query, 'stock_transfers.from_branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['stock_transfers.number', 'source.name', 'target.name'])
                ->orderable([
                    null, 'stock_transfers.number', 'stock_transfers.transfer_date',
                    'source.name', 'target.name', 'stock_transfers.total_value',
                    'stock_transfers.status', null,
                ])
                ->filter('warehouse', fn ($q, $value) => $q->where(fn ($sub) => $sub
                    ->where('stock_transfers.from_warehouse_id', $value)
                    ->orWhere('stock_transfers.to_warehouse_id', $value)))
                ->filter('status', fn ($q, $value) => $q->where('stock_transfers.status', $value))
                ->transform(fn (StockTransfer $transfer) => [
                    'number' => '<a class="font-mono text-xs hover:text-brand-600" href="'
                        .route('inventory.transfers.detail', $transfer).'">'.e($transfer->number).'</a>',
                    'date' => $transfer->transfer_date->format('d/m/Y'),
                    'from' => e((string) $transfer->from_name),
                    'to' => e((string) $transfer->to_name),
                    'value' => Money::rupiah($transfer->total_value),
                    'status' => view('components.status-badge', ['status' => $transfer->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'detail' => route('inventory.transfers.detail', $transfer),
                        'edit' => $transfer->status->isEditable() ? route('inventory.transfers.edit', $transfer) : null,
                        'delete' => $transfer->status->isEditable() ? route('inventory.transfers.hapus', $transfer) : null,
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(): View
    {
        return view('inventory.transfer.create');
    }

    public function edit(StockTransfer $stockTransfer): View
    {
        abort_unless($stockTransfer->status->isEditable(), 403);

        return view('inventory.transfer.edit', ['transfer' => $stockTransfer]);
    }

    public function detail(StockTransfer $stockTransfer): View
    {
        $stockTransfer->load([
            'items.product.baseUnit', 'items.unit', 'items.batch',
            'fromWarehouse', 'toWarehouse', 'creator',
        ]);

        return view('inventory.transfer.detail', ['transfer' => $stockTransfer]);
    }

    public function ship(StockTransfer $stockTransfer): RedirectResponse
    {
        try {
            $this->transfers->ship($stockTransfer);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Barang berhasil dikirim dan stok gudang asal sudah berkurang.');
    }

    public function receive(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        $validated = $request->validate([
            'received' => ['array'],
            'received.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->transfers->receive(
                $stockTransfer,
                array_map('floatval', array_filter($validated['received'] ?? [], fn ($v) => $v !== null && $v !== '')),
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Penerimaan transfer berhasil dicatat.');
    }

    public function hapus(StockTransfer $stockTransfer): RedirectResponse
    {
        try {
            $this->transfers->cancel($stockTransfer);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Transfer dibatalkan.');
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            DocumentStatus::Draft->value => DocumentStatus::Draft->label(),
            DocumentStatus::PartiallyProcessed->value => 'Dalam Perjalanan',
            DocumentStatus::Completed->value => DocumentStatus::Completed->label(),
            DocumentStatus::Cancelled->value => DocumentStatus::Cancelled->label(),
        ];
    }
}
