<?php

declare(strict_types=1);

namespace App\Modules\Supplier\Controllers;

use App\Modules\Core\Enums\PaymentTermType;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Supplier\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController
{
    public function index(): View
    {
        return view('supplier.supplier.index', [
            'paymentTerms' => PaymentTermType::options(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Supplier::query()->select('suppliers.*');

        return response()->json(
            ServerTable::of($query)
                ->searchable(['suppliers.code', 'suppliers.name', 'suppliers.phone', 'suppliers.city'])
                ->orderable([
                    null, 'suppliers.code', 'suppliers.name', 'suppliers.city',
                    'suppliers.payment_term', 'suppliers.lead_time_days',
                    'suppliers.outstanding_amount', 'suppliers.on_time_rate', null, null,
                ])
                ->filter('payment_term', fn ($q, $value) => $q->where('suppliers.payment_term', $value))
                ->filter('is_active', fn ($q, $value) => $q->where('suppliers.is_active', $value === '1'))
                ->transform(fn (Supplier $supplier) => [
                    'code' => '<span class="font-mono text-xs">'.e($supplier->code).'</span>',
                    'name' => '<a class="font-medium hover:text-brand-600" href="'.route('suppliers.detail', $supplier).'">'
                        .e($supplier->name).'</a>',
                    'city' => e((string) $supplier->city),
                    'payment_term' => e($supplier->payment_term->label()),
                    'lead_time' => $supplier->lead_time_days.' hari',
                    'outstanding' => Money::rupiah($supplier->outstanding_amount),
                    'performance' => '<span class="badge-'.$supplier->performanceColor().'">'
                        .e($supplier->performanceLabel()).'</span>',
                    'status' => $supplier->is_active
                        ? '<span class="badge-success">Aktif</span>'
                        : '<span class="badge-muted">Nonaktif</span>',
                    'aksi' => view('components.row-actions', [
                        'detail' => route('suppliers.detail', $supplier),
                        'edit' => auth()->user()->can('supplier.edit') ? route('suppliers.edit', $supplier) : null,
                        'delete' => auth()->user()->can('supplier.delete') ? route('suppliers.hapus', $supplier) : null,
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(): View
    {
        return view('supplier.supplier.create');
    }

    public function edit(Supplier $supplier): View
    {
        return view('supplier.supplier.edit', ['supplier' => $supplier]);
    }

    public function detail(Supplier $supplier): View
    {
        $supplier->load(['taxCode', 'products.product', 'products.unit']);

        return view('supplier.supplier.detail', [
            'supplier' => $supplier,
            'history' => $supplier->priceHistory()
                ->with('product', 'unit')
                ->orderByDesc('effective_date')
                ->limit(20)
                ->get(),
        ]);
    }

    /**
     * Perbandingan supplier berdasarkan ketepatan kirim dan kualitas (PLAN 21),
     * dipakai purchasing sebelum memilih supplier untuk PO berikutnya.
     */
    public function performance(): View
    {
        $suppliers = Supplier::active()
            ->orderByDesc('order_count')
            ->orderBy('name')
            ->get();

        return view('supplier.supplier.performance', [
            'suppliers' => $suppliers,
            'best' => $suppliers->where('order_count', '>', 0)
                ->sortByDesc(fn (Supplier $supplier) => $supplier->performanceScore())
                ->take(5),
            'attention' => $suppliers->where('order_count', '>', 0)
                ->filter(fn (Supplier $supplier) => $supplier->performanceScore() < 75)
                ->sortBy(fn (Supplier $supplier) => $supplier->performanceScore()),
        ]);
    }

    public function hapus(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return back()->with('status', 'Supplier berhasil dihapus.');
    }
}
