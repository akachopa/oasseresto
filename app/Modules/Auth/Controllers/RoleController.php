<?php

declare(strict_types=1);

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Support\PermissionRegistry;
use App\Modules\Auth\Support\RoleTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

class RoleController
{
    public function index(): View
    {
        return view('auth.role.index', [
            'roles' => Role::where('company_id', auth()->user()->company_id)
                ->withCount('permissions', 'users')
                ->orderBy('name')
                ->get(),
            'templates' => RoleTemplate::all(),
        ]);
    }

    public function create(): View
    {
        return view('auth.role.form', [
            'role' => new Role,
            'granted' => [],
            'modules' => PermissionRegistry::modules(),
        ]);
    }

    public function edit(Role $role): View
    {
        abort_unless((int) $role->company_id === (int) auth()->user()->company_id, 404);

        return view('auth.role.form', [
            'role' => $role,
            'granted' => $role->permissions->pluck('name')->all(),
            'modules' => PermissionRegistry::modules(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = (int) auth()->user()->company_id;

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('roles', 'name')->where('company_id', $companyId)->where('guard_name', 'web'),
            ],
            'template' => ['nullable', 'string'],
            'permissions' => ['array'],
        ]);

        setPermissionsTeamId($companyId);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'company_id' => $companyId,
        ]);

        $role->syncPermissions($this->resolvePermissions($validated));

        return redirect()->route('team.roles.index')->with('status', 'Role berhasil dibuat.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless((int) $role->company_id === (int) auth()->user()->company_id, 404);

        $validated = $request->validate([
            'template' => ['nullable', 'string'],
            'permissions' => ['array'],
        ]);

        setPermissionsTeamId((int) $role->company_id);
        $role->syncPermissions($this->resolvePermissions($validated));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        activity()
            ->performedOn($role)
            ->causedBy(auth()->user())
            ->event('permission_changed')
            ->log("Permission role {$role->name} diubah");

        return redirect()->route('team.roles.index')->with('status', "Permission role {$role->name} berhasil disimpan.");
    }

    public function hapus(Role $role): RedirectResponse
    {
        abort_unless((int) $role->company_id === (int) auth()->user()->company_id, 404);

        if ($role->users()->exists()) {
            return back()->with('error', 'Role masih dipakai oleh user.');
        }

        $role->delete();

        return back()->with('status', 'Role berhasil dihapus.');
    }

    /**
     * Template dipakai sebagai titik awal, lalu ditimpa pilihan manual.
     */
    private function resolvePermissions(array $validated): array
    {
        $permissions = $validated['permissions'] ?? [];

        if ($permissions === [] && ! empty($validated['template'])) {
            $permissions = RoleTemplate::resolve($validated['template']);
        }

        return array_values(array_intersect($permissions, PermissionRegistry::all()));
    }
}
