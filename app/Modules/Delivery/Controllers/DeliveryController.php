<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Controllers;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DeliveryStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Services\DeliveryService;
use App\Modules\Sales\Models\SalesOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class DeliveryController
{
    public function __construct(
        private readonly DeliveryService $deliveries,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        return view('delivery.order.index', [
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'statuses' => self::statusOptions(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json($this->table($request));
    }

    /**
     * Layar picking hanya menampilkan surat jalan yang masih perlu disiapkan
     * gudang, supaya petugas tidak perlu memfilter sendiri (PLAN 27).
     */
    public function picking(): View
    {
        return view('delivery.picking.index', [
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function pickingData(Request $request): JsonResponse
    {
        return response()->json($this->table($request, openOnly: true));
    }

    public function pick(Delivery $delivery): View
    {
        abort_unless($delivery->isPickable(), 403);

        $delivery->load(['items.product.baseUnit', 'items.unit', 'customer', 'warehouse', 'order']);

        return view('delivery.picking.pick', ['delivery' => $delivery]);
    }

    public function storePick(Request $request, Delivery $delivery): RedirectResponse
    {
        $validated = $request->validate([
            'picked' => ['array'],
            'picked.*' => ['nullable', 'numeric', 'min:0'],
            'batch' => ['array'],
            'batch.*' => ['nullable', 'integer', 'exists:batches,id'],
        ]);

        try {
            $this->deliveries->pick(
                $delivery,
                array_map('floatval', array_filter(
                    $validated['picked'] ?? [],
                    fn ($value) => $value !== null && $value !== '',
                )),
                array_filter($validated['batch'] ?? [], fn ($value) => $value !== null && $value !== ''),
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('delivery.orders.detail', $delivery)
            ->with('status', 'Picking selesai; surat jalan siap dikirim.');
    }

    public function create(Request $request): View
    {
        $orderId = $request->integer('order') ?: null;

        if ($orderId !== null) {
            $order = SalesOrder::findOrFail($orderId);

            abort_unless($order->isDeliverable(), 403);
        }

        return view('delivery.order.create', ['orderId' => $orderId]);
    }

    public function edit(Delivery $delivery): View
    {
        abort_unless($delivery->isEditable(), 403);

        return view('delivery.order.edit', ['delivery' => $delivery]);
    }

    public function detail(Delivery $delivery): View
    {
        $delivery->load([
            'items.product.baseUnit', 'items.unit', 'items.batch', 'items.orderItem',
            'customer', 'warehouse', 'order', 'picker', 'creator',
        ]);

        return view('delivery.order.detail', ['delivery' => $delivery]);
    }

    public function print(Delivery $delivery): View
    {
        $delivery->load(['items.product', 'items.unit', 'customer', 'warehouse', 'branch', 'company', 'order']);

        return view('delivery.order.print', ['delivery' => $delivery]);
    }

    public function dispatchDelivery(Delivery $delivery): RedirectResponse
    {
        try {
            $this->deliveries->dispatch($delivery);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Barang dikirim dan stok gudang sudah berkurang.');
    }

    public function complete(Request $request, Delivery $delivery): RedirectResponse
    {
        $validated = $request->validate([
            'recipient_name' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $this->deliveries->complete($delivery, $validated['recipient_name'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Pengiriman ditandai diterima customer.');
    }

    public function fail(Request $request, Delivery $delivery): RedirectResponse
    {
        $validated = $request->validate([
            'failure_reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->deliveries->fail($delivery, $validated['failure_reason']);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Pengiriman ditandai gagal; barang dikembalikan ke stok.');
    }

    public function hapus(Delivery $delivery): RedirectResponse
    {
        try {
            $this->deliveries->cancel($delivery);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('delivery.orders.index')
            ->with('status', 'Surat jalan dibatalkan.');
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            DeliveryStatus::Ready->value => 'Siap Picking',
            DeliveryStatus::Packed->value => DeliveryStatus::Packed->label(),
            DeliveryStatus::Dispatched->value => 'Dalam Pengiriman',
            DeliveryStatus::Delivered->value => 'Diterima Customer',
            DeliveryStatus::Failed->value => DeliveryStatus::Failed->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function table(Request $request, bool $openOnly = false): array
    {
        $query = Delivery::query()
            ->join('customers', 'customers.id', '=', 'deliveries.customer_id')
            ->join('warehouses', 'warehouses.id', '=', 'deliveries.warehouse_id')
            ->leftJoin('sales_orders', 'sales_orders.id', '=', 'deliveries.sales_order_id')
            ->select([
                'deliveries.*',
                'customers.name as customer_name',
                'warehouses.name as warehouse_name',
                'sales_orders.number as order_number',
            ])
            ->when($openOnly, fn ($builder) => $builder->open());

        $this->location->applyBranch($query, 'deliveries.branch_id', includeNull: true);

        return ServerTable::of($query)
            ->searchable(['deliveries.number', 'customers.name', 'sales_orders.number', 'deliveries.driver_name'])
            ->orderable([
                null, 'deliveries.number', 'deliveries.delivery_date',
                'customers.name', 'warehouses.name', 'sales_orders.number',
                'deliveries.status', null,
            ])
            ->filter('warehouse', fn ($q, $value) => $q->where('deliveries.warehouse_id', $value))
            ->filter('status', fn ($q, $value) => $q->where('deliveries.status', $value))
            ->transform(fn (Delivery $delivery) => [
                'number' => '<a class="font-mono text-xs hover:text-brand-600" href="'
                    .route('delivery.orders.detail', $delivery).'">'.e($delivery->number).'</a>',
                'date' => $delivery->delivery_date->format('d/m/Y'),
                'customer' => e((string) $delivery->customer_name),
                'warehouse' => e((string) $delivery->warehouse_name),
                'order' => $delivery->order_number
                    ? '<span class="font-mono text-xs">'.e((string) $delivery->order_number).'</span>'
                    : '<span class="text-muted">tanpa SO</span>',
                'status' => view('components.status-badge', ['status' => $delivery->status])->render(),
                'aksi' => view('components.row-actions', [
                    'detail' => route('delivery.orders.detail', $delivery),
                    'edit' => $delivery->isEditable() ? route('delivery.orders.edit', $delivery) : null,
                    'delete' => $delivery->isEditable() ? route('delivery.orders.hapus', $delivery) : null,
                    'extra' => $delivery->isPickable()
                        ? [['url' => route('delivery.picking.pick', $delivery), 'label' => 'Picking', 'icon' => 'clipboard']]
                        : [],
                ])->render(),
            ])
            ->make($request);
    }
}
