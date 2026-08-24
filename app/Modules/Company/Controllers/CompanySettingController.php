<?php

declare(strict_types=1);

namespace App\Modules\Company\Controllers;

use App\Modules\Company\Models\Company;
use App\Modules\Core\Enums\CostingMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanySettingController
{
    public function index(): View
    {
        return view('company.setting', [
            'company' => Company::findOrFail(auth()->user()->company_id),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = Company::findOrFail(auth()->user()->company_id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'fiscal_year_start' => ['nullable', 'date'],
            'costing_method' => ['required', 'in:fifo,weighted_average'],
            'credit_mode' => ['required', 'in:block,warn,approval'],
            'min_margin_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'near_expiry_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $company->fill([
            ...array_diff_key($validated, array_flip(['credit_mode', 'min_margin_percent', 'near_expiry_days'])),
            'allow_negative_stock' => $request->boolean('allow_negative_stock'),
            'settings' => [
                ...($company->settings ?? []),
                'credit_mode' => $validated['credit_mode'],
                'min_margin_percent' => (float) $validated['min_margin_percent'],
                'near_expiry_days' => (int) $validated['near_expiry_days'],
            ],
        ])->save();

        return back()->with('status', 'Pengaturan company berhasil disimpan.');
    }

    public function costingMethods(): array
    {
        return array_reduce(
            CostingMethod::cases(),
            fn (array $carry, CostingMethod $method) => $carry + [$method->value => $method->label()],
            [],
        );
    }
}
