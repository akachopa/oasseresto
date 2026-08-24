<?php

declare(strict_types=1);

namespace App\Modules\Customer\Controllers;

use App\Models\User;
use App\Modules\Core\Scopes\LocationScope;
use App\Modules\Core\Support\Money;
use App\Modules\Core\Support\ServerTable;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController
{
    public function index(): View
    {
        return view('customer.customer.index', [
            'groups' => CustomerGroup::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'salesmen' => User::whereHas('roles', fn ($q) => $q->where('name', 'Salesman'))
                ->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $query = Customer::query()
            ->leftJoin('customer_groups', 'customer_groups.id', '=', 'customers.customer_group_id')
            ->leftJoin('users', 'users.id', '=', 'customers.salesman_id')
            ->select([
                'customers.*',
                'customer_groups.name as group_name',
                'users.name as salesman_name',
            ]);

        app(LocationScope::class)->applyBranch($query, 'customers.branch_id', includeNull: true);

        return response()->json(
            ServerTable::of($query)
                ->searchable(['customers.code', 'customers.name', 'customers.phone', 'customers.city'])
                ->orderable([
                    null, 'customers.code', 'customers.name', 'customer_groups.name',
                    'customers.city', 'users.name', 'customers.credit_limit',
                    'customers.outstanding_amount', null, null,
                ])
                ->filter('group', fn ($q, $value) => $q->where('customers.customer_group_id', $value))
                ->filter('salesman', fn ($q, $value) => $q->where('customers.salesman_id', $value))
                ->filter('is_active', fn ($q, $value) => $q->where('customers.is_active', $value === '1'))
                ->filter('overdue', fn ($q) => $q->where('customers.overdue_amount', '>', 0))
                ->transform(fn (Customer $customer) => [
                    'code' => '<span class="font-mono text-xs">'.e($customer->code).'</span>',
                    'name' => '<a class="font-medium hover:text-brand-600" href="'.route('customers.detail', $customer).'">'
                        .e($customer->name).'</a>',
                    'group' => e((string) $customer->group_name),
                    'city' => e((string) $customer->city),
                    'salesman' => e((string) $customer->salesman_name),
                    'credit_limit' => Money::rupiah($customer->credit_limit),
                    'outstanding' => $customer->overdue_amount > 0
                        ? '<span class="text-negative font-medium">'.Money::rupiah($customer->outstanding_amount).'</span>'
                        : Money::rupiah($customer->outstanding_amount),
                    'status' => $customer->is_active
                        ? '<span class="badge-success">Aktif</span>'
                        : '<span class="badge-muted">Nonaktif</span>',
                    'aksi' => view('components.row-actions', [
                        'detail' => route('customers.detail', $customer),
                        'edit' => auth()->user()->can('customer.edit') ? route('customers.edit', $customer) : null,
                        'delete' => auth()->user()->can('customer.delete') ? route('customers.hapus', $customer) : null,
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(): View
    {
        return view('customer.customer.create');
    }

    public function edit(Customer $customer): View
    {
        return view('customer.customer.edit', ['customer' => $customer]);
    }

    public function detail(Customer $customer): View
    {
        $customer->load(['group', 'priceLevel', 'branch', 'salesman', 'taxCode', 'addresses', 'creditProfile']);

        return view('customer.customer.detail', ['customer' => $customer]);
    }

    public function hapus(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return back()->with('status', 'Customer berhasil dihapus.');
    }
}
