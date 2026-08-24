<?php

declare(strict_types=1);

namespace App\Modules\Core\Controllers;

use App\Modules\Core\Models\TaxCode;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaxCodeController
{
    public function index(): View
    {
        return view('core.tax-code.index');
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json(
            ServerTable::of(TaxCode::query())
                ->searchable(['code', 'name'])
                ->orderable([null, 'code', 'name', 'rate', null, null, null])
                ->transform(fn (TaxCode $tax) => [
                    'code' => e($tax->code),
                    'name' => e($tax->name),
                    'rate' => Money::percent($tax->rate, 2),
                    'inclusive' => $tax->is_inclusive
                        ? '<span class="badge-info">Termasuk</span>'
                        : '<span class="badge-muted">Belum termasuk</span>',
                    'usage' => implode(' ', array_filter([
                        $tax->for_sales ? '<span class="badge-success">Penjualan</span>' : null,
                        $tax->for_purchase ? '<span class="badge-warning">Pembelian</span>' : null,
                    ])),
                    'status' => $tax->is_active
                        ? '<span class="badge-success">Aktif</span>'
                        : '<span class="badge-muted">Nonaktif</span>',
                    'aksi' => view('components.row-actions', [
                        'edit' => route('settings.tax-codes.edit', $tax),
                        'delete' => route('settings.tax-codes.hapus', $tax),
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(): View
    {
        return view('core.tax-code.form', [
            'taxCode' => new TaxCode(['is_active' => true, 'for_sales' => true, 'for_purchase' => true, 'rate' => 11]),
        ]);
    }

    public function edit(TaxCode $taxCode): View
    {
        return view('core.tax-code.form', ['taxCode' => $taxCode]);
    }

    public function store(Request $request): RedirectResponse
    {
        TaxCode::create($this->validated($request));

        return redirect()->route('settings.tax-codes.index')->with('status', 'Kode pajak berhasil ditambahkan.');
    }

    public function update(Request $request, TaxCode $taxCode): RedirectResponse
    {
        $taxCode->update($this->validated($request, $taxCode));

        return redirect()->route('settings.tax-codes.index')->with('status', 'Kode pajak berhasil diperbarui.');
    }

    public function hapus(TaxCode $taxCode): RedirectResponse
    {
        $taxCode->delete();

        return back()->with('status', 'Kode pajak berhasil dihapus.');
    }

    private function validated(Request $request, ?TaxCode $taxCode = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('tax_codes', 'code')
                    ->where('company_id', $taxCode?->company_id ?? auth()->user()->company_id)
                    ->ignore($taxCode?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]) + [
            'is_inclusive' => $request->boolean('is_inclusive'),
            'for_sales' => $request->boolean('for_sales'),
            'for_purchase' => $request->boolean('for_purchase'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
