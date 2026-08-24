<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\UserScope;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Warehouse;
use App\Modules\Core\Enums\ScopeLevel;
use App\Modules\Core\Support\ServerTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController
{
    public function index(): View
    {
        return view('auth.user.index');
    }

    public function data(Request $request): JsonResponse
    {
        $companyId = (int) auth()->user()->company_id;

        $query = User::query()
            ->where('users.company_id', $companyId)
            ->leftJoin('branches', 'branches.id', '=', 'users.default_branch_id')
            ->select([
                'users.id', 'users.name', 'users.email', 'users.username',
                'users.job_title', 'users.is_active', 'users.last_login_at',
                'branches.name as branch_name',
            ])
            ->with('scopes.branch', 'scopes.warehouse', 'roles');

        return response()->json(
            ServerTable::of($query)
                ->searchable(['users.name', 'users.email', 'users.username', 'users.job_title'])
                ->orderable([null, 'users.name', 'users.email', null, null, 'branches.name', 'users.last_login_at', null])
                ->filter('is_active', fn ($q, $value) => $q->where('users.is_active', $value === '1'))
                ->filter('role', fn ($q, $value) => $q->whereHas('roles', fn ($r) => $r->where('name', $value)))
                ->transform(fn (User $user) => [
                    'name' => '<span class="font-medium">'.e($user->name).'</span>'
                        .'<span class="text-muted block text-xs">'.e((string) $user->job_title).'</span>',
                    'email' => e($user->email).'<span class="text-muted block text-xs">'.e((string) $user->username).'</span>',
                    'roles' => $user->roles->map(fn ($role) => '<span class="badge-info">'.e($role->name).'</span>')->join(' '),
                    'scope' => $user->scopes->map(fn (UserScope $scope) => e($scope->describe()))->join('<br>'),
                    'branch' => e((string) $user->branch_name),
                    'last_login' => $user->last_login_at?->diffForHumans() ?? '<span class="text-muted">Belum pernah</span>',
                    'aksi' => view('components.row-actions', [
                        'edit' => route('team.users.edit', $user),
                        'delete' => $user->is(auth()->user()) ? null : route('team.users.hapus', $user),
                    ])->render(),
                ])
                ->make($request),
        );
    }

    public function create(): View
    {
        return view('auth.user.form', $this->formData(new User(['is_active' => true])));
    }

    public function edit(User $user): View
    {
        abort_unless($user->company_id === auth()->user()->company_id, 404);

        return view('auth.user.form', $this->formData($user));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated, $request): void {
            $user = User::create([
                'company_id' => auth()->user()->company_id,
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'job_title' => $validated['job_title'] ?? null,
                'password' => Hash::make($validated['password']),
                'default_branch_id' => $validated['default_branch_id'] ?? null,
                'is_active' => $request->boolean('is_active'),
                'email_verified_at' => now(),
            ]);

            $user->syncRoles($validated['roles']);
            $this->syncScopes($user, $request);
        });

        return redirect()->route('team.users.index')->with('status', 'User berhasil ditambahkan.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->company_id === auth()->user()->company_id, 404);

        $validated = $this->validated($request, $user);

        DB::transaction(function () use ($user, $validated, $request): void {
            $user->fill([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'job_title' => $validated['job_title'] ?? null,
                'default_branch_id' => $validated['default_branch_id'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ]);

            if (! empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            $user->save();
            $user->syncRoles($validated['roles']);
            $this->syncScopes($user, $request);
        });

        return redirect()->route('team.users.index')->with('status', 'User berhasil diperbarui.');
    }

    public function hapus(User $user): RedirectResponse
    {
        abort_unless($user->company_id === auth()->user()->company_id, 404);

        if ($user->is(auth()->user())) {
            return back()->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }

        $user->delete();

        return back()->with('status', 'User berhasil dihapus.');
    }

    private function formData(User $user): array
    {
        return [
            'user' => $user,
            'roles' => Role::where('company_id', auth()->user()->company_id)->orderBy('name')->pluck('name'),
            'branches' => Branch::active()->orderBy('name')->get(),
            'warehouses' => Warehouse::active()->with('branch')->orderBy('name')->get(),
            'currentRoles' => $user->exists ? $user->roles->pluck('name')->all() : [],
            'currentScopes' => $user->exists ? $user->scopes : collect(),
        ];
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $companyId = auth()->user()->company_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required', 'string', 'max:60',
                Rule::unique('users', 'username')->where('company_id', $companyId)->ignore($user?->id),
            ],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'default_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('company_id', $companyId)],
            'scope_level' => ['required', 'in:company,branch,warehouse'],
            'scope_branches' => ['array'],
            'scope_branches.*' => ['integer', 'exists:branches,id'],
            'scope_warehouses' => ['array'],
            'scope_warehouses.*' => ['integer', 'exists:warehouses,id'],
        ]);
    }

    /**
     * Location scope: company (semua), branch tertentu, atau warehouse tertentu.
     */
    private function syncScopes(User $user, Request $request): void
    {
        UserScope::where('user_id', $user->id)->delete();

        $level = ScopeLevel::from($request->string('scope_level')->toString());
        $companyId = (int) $user->company_id;

        if ($level === ScopeLevel::Company) {
            UserScope::create([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'level' => ScopeLevel::Company,
            ]);

            return;
        }

        if ($level === ScopeLevel::Branch) {
            foreach ((array) $request->input('scope_branches', []) as $branchId) {
                UserScope::create([
                    'company_id' => $companyId,
                    'user_id' => $user->id,
                    'level' => ScopeLevel::Branch,
                    'branch_id' => (int) $branchId,
                ]);
            }

            return;
        }

        foreach ((array) $request->input('scope_warehouses', []) as $warehouseId) {
            $warehouse = Warehouse::find($warehouseId);

            if (! $warehouse) {
                continue;
            }

            UserScope::create([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'level' => ScopeLevel::Warehouse,
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
            ]);
        }
    }
}
