<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Controllers;

use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\DocumentStatus;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Inventory\Models\StockOpname;
use App\Modules\Inventory\Services\StockOpnameService;
use App\Modules\Product\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class StockOpnameController
{
    public function __construct(
        private readonly StockOpnameService $opnames,
        private readonly LocationScope $location,
    ) {}

    public function index(): View
    {
        return view('inventory.opname.index', [
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = StockOpname::query()
            ->join('warehouses', 'warehouses.id', '=', 'stock_opnames.warehouse_id')
            ->select(['stock_opnames.*', 'warehouses.name as warehouse_name']);

        $this->location->applyBranch($query, 'stock_opnames.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['stock_opnames.number', 'warehouses.name'])
                ->orderable([
                    null, 'stock_opnames.number', 'stock_opnames.opname_date', 'warehouses.name',
                    'stock_opnames.difference_count', 'stock_opnames.difference_value',
                    'stock_opnames.status', null,
                ])
                ->filter('warehouse', fn ($q, $value) => $q->where('stock_opnames.warehouse_id', $value))
                ->filter('status', fn ($q, $value) => $q->where('stock_opnames.status', $value))
                ->transform(fn (StockOpname $opname) => [
                    'number' => '<a class="font-mono text-xs hover:text-brand-600" href="'
                        .route('inventory.opnames.detail', $opname).'">'.e($opname->number).'</a>',
                    'date' => $opname->opname_date->format('d/m/Y'),
                    'warehouse' => e((string) $opname->warehouse_name),
                    'difference_count' => (string) $opname->difference_count,
                    'difference_value' => $this->valueCell($opname),
                    'status' => view('components.status-badge', ['status' => $opname->status])->render(),
                    'aksi' => view('components.row-actions', [
                        'detail' => route('inventory.opnames.detail', $opname),
                        'edit' => $opname->isCountable() ? route('inventory.opnames.count', $opname) : null,
                        'delete' => $opname->isCountable() ? route('inventory.opnames.hapus', $opname) : null,
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(): View
    {
        return view('inventory.opname.create', [
            'warehouses' => Warehouse::active()->orderBy('name')->pluck('name', 'id'),
            'categories' => ProductCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'opname_date' => ['required', 'date'],
            'scope_type' => ['required', 'in:full,category'],
            'product_category_id' => ['nullable', 'integer', 'exists:product_categories,id', 'required_if:scope_type,category'],
            'note' => ['nullable', 'string'],
        ], [
            'product_category_id.required_if' => 'Pilih kategori bila opname dibatasi per kategori.',
        ]);

        if ($validated['scope_type'] === 'full') {
            $validated['product_category_id'] = null;
        }

        $opname = $this->opnames->create($validated);

        return redirect()->route('inventory.opnames.count', $opname)
            ->with('status', 'Lembar hitung berhasil dibuat.');
    }

    public function count(StockOpname $stockOpname): View
    {
        abort_unless($stockOpname->isCountable(), 403);

        return view('inventory.opname.count', ['opname' => $stockOpname]);
    }

    public function detail(StockOpname $stockOpname): View
    {
        $stockOpname->load(['items.product.baseUnit', 'items.batch', 'warehouse', 'counter']);

        return view('inventory.opname.detail', ['opname' => $stockOpname]);
    }

    public function post(StockOpname $stockOpname): RedirectResponse
    {
        try {
            $this->opnames->post($stockOpname);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('inventory.opnames.detail', $stockOpname)
            ->with('status', 'Opname diposting dan selisihnya sudah masuk kartu stok.');
    }

    public function hapus(StockOpname $stockOpname): RedirectResponse
    {
        try {
            $this->opnames->cancel($stockOpname);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Opname dibatalkan.');
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            DocumentStatus::Draft->value => 'Sedang Dihitung',
            DocumentStatus::Submitted->value => DocumentStatus::Submitted->label(),
            DocumentStatus::Posted->value => DocumentStatus::Posted->label(),
            DocumentStatus::Cancelled->value => DocumentStatus::Cancelled->label(),
        ];
    }

    private function valueCell(StockOpname $opname): string
    {
        $class = $opname->difference_value < 0 ? 'text-negative' : 'text-positive';

        return '<span class="'.$class.'">'.Money::rupiah($opname->difference_value).'</span>';
    }
}
