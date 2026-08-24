<?php

declare(strict_types=1);

namespace App\Modules\Company\Controllers;

use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Support\ServerTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarehouseController
{
    public function index(): View
    {
        return view('company.warehouse.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = Warehouse::query()
            ->join('branches', 'branches.id', '=', 'warehouses.branch_id')
            ->select([
                'warehouses.id', 'warehouses.code', 'warehouses.name', 'warehouses.type',
                'warehouses.is_sellable', 'warehouses.is_active', 'warehouses.is_default',
                'branches.name as branch_name',
            ]);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['warehouses.code', 'warehouses.name', 'branches.name'])
                ->orderable([null, 'warehouses.code', 'warehouses.name', 'branches.name', 'warehouses.type', null, null])
                ->filter('branch_id', fn ($q, $value) => $q->where('warehouses.branch_id', $value))
                ->filter('is_active', fn ($q, $value) => $q->where('warehouses.is_active', $value === '1'))
                ->transform(fn (Warehouse $warehouse) => [
                    'code' => e($warehouse->code),
                    'name' => e($warehouse->name).($warehouse->is_default ? ' <span class="badge-info">Default</span>' : ''),
                    'branch' => e((string) $warehouse->branch_name),
                    'type' => e($warehouse->type),
                    'sellable' => $warehouse->is_sellable
                        ? '<span class="badge-success">Bisa dijual</span>'
                        : '<span class="badge-muted">Non-jual</span>',
                    'status' => $warehouse->is_active
                        ? '<span class="badge-success">Aktif</span>'
                        : '<span class="badge-muted">Nonaktif</span>',
                    'aksi' => view('components.row-actions', [
                        'edit' => route('settings.warehouses.edit', $warehouse),
                        'delete' => $warehouse->is_default ? null : route('settings.warehouses.hapus', $warehouse),
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(): View
    {
        return view('company.warehouse.form', [
            'warehouse' => new Warehouse(['is_active' => true, 'is_sellable' => true, 'type' => 'main']),
            'branches' => Branch::active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('company.warehouse.form', [
            'warehouse' => $warehouse,
            'branches' => Branch::active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Warehouse::create($this->validated($request));

        return redirect()->route('settings.warehouses.index')->with('status', 'Gudang berhasil ditambahkan.');
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($this->validated($request, $warehouse));

        return redirect()->route('settings.warehouses.index')->with('status', 'Gudang berhasil diperbarui.');
    }

    public function hapus(Warehouse $warehouse): RedirectResponse
    {
        if ($warehouse->is_default) {
            return back()->with('error', 'Gudang default tidak dapat dihapus.');
        }

        $warehouse->delete();

        return back()->with('status', 'Gudang berhasil dihapus.');
    }

    private function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('warehouses', 'code')
                    ->where('company_id', $warehouse?->company_id ?? auth()->user()->company_id)
                    ->ignore($warehouse?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'type' => ['required', 'in:main,outlet,transit,damaged'],
            'address' => ['nullable', 'string'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
            'is_sellable' => $request->boolean('is_sellable'),
        ];
    }
}
