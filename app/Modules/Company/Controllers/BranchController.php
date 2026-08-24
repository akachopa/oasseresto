<?php

declare(strict_types=1);

namespace App\Modules\Company\Controllers;

use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\BusinessUnit;
use App\Modules\Core\Support\ServerTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController
{
    public function index(): View
    {
        return view('company.branch.index');
    }

    public function data(Request $request): JsonResponse
    {
        $query = Branch::query()
            ->leftJoin('business_units', 'business_units.id', '=', 'branches.business_unit_id')
            ->select([
                'branches.id', 'branches.code', 'branches.name', 'branches.city',
                'branches.type', 'branches.is_active', 'branches.is_default',
                'business_units.name as business_unit_name',
            ])
            ->withCount('warehouses');

        return response()->json(
            ServerTable::of($query)
                ->searchable(['branches.code', 'branches.name', 'branches.city'])
                ->orderable([null, 'branches.code', 'branches.name', 'branches.city', 'business_units.name', null, null])
                ->filter('is_active', fn ($q, $value) => $q->where('branches.is_active', $value === '1'))
                ->transform(fn (Branch $branch) => [
                    'code' => e($branch->code),
                    'name' => e($branch->name).($branch->is_default ? ' <span class="badge-info">Default</span>' : ''),
                    'city' => e((string) $branch->city),
                    'business_unit' => e((string) $branch->business_unit_name),
                    'warehouses' => $branch->warehouses_count.' gudang',
                    'status' => $branch->is_active
                        ? '<span class="badge-success">Aktif</span>'
                        : '<span class="badge-muted">Nonaktif</span>',
                    'aksi' => view('components.row-actions', [
                        'edit' => route('settings.branches.edit', $branch),
                        'delete' => $branch->is_default ? null : route('settings.branches.hapus', $branch),
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(): View
    {
        return view('company.branch.form', [
            'branch' => new Branch(['is_active' => true, 'type' => 'branch']),
            'businessUnits' => BusinessUnit::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function edit(Branch $branch): View
    {
        return view('company.branch.form', [
            'branch' => $branch,
            'businessUnits' => BusinessUnit::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Branch::create($this->validated($request));

        return redirect()->route('settings.branches.index')->with('status', 'Cabang berhasil ditambahkan.');
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $branch->update($this->validated($request, $branch));

        return redirect()->route('settings.branches.index')->with('status', 'Cabang berhasil diperbarui.');
    }

    public function hapus(Branch $branch): RedirectResponse
    {
        if ($branch->is_default) {
            return back()->with('error', 'Cabang default tidak dapat dihapus.');
        }

        if ($branch->warehouses()->exists()) {
            return back()->with('error', 'Cabang masih memiliki gudang aktif.');
        }

        $branch->delete();

        return back()->with('status', 'Cabang berhasil dihapus.');
    }

    private function validated(Request $request, ?Branch $branch = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('branches', 'code')
                    ->where('company_id', $branch?->company_id ?? auth()->user()->company_id)
                    ->ignore($branch?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'business_unit_id' => ['nullable', 'integer', 'exists:business_units,id'],
            'type' => ['required', 'in:branch,outlet,head_office'],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
