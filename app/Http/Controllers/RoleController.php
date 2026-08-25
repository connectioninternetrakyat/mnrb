<?php

namespace App\Http\Controllers;

use App\Enums\MenuPermission;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a list of roles.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::RolesView->value);

        $menuLabels = array_column(MenuPermission::options(), 'label', 'value');

        $roles = Role::query()
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'users_count' => $role->users_count,
                'locked' => $role->name === UserRole::SuperAdmin->value,
                'menus' => collect($role->permissions->pluck('name'))
                    ->filter(fn (string $name): bool => MenuPermission::isMenuPermission($name))
                    ->map(fn (string $name): string => $menuLabels[$name] ?? $name)
                    ->values()
                    ->all(),
            ])
            ->all();

        return Inertia::render('roles/index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        Role::create($request->validated() + [
            'guard_name' => 'web',
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role berhasil ditambahkan.')]);

        return to_route('roles.index');
    }

    /**
     * Update the given role.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->ensureRoleIsManageable($role);

        $role->update($request->safe()->only(['name', 'description']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role berhasil diperbarui.')]);

        return to_route('roles.index');
    }

    /**
     * Remove the given role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize(UserPermission::RolesDelete->value);
        $this->ensureRoleIsManageable($role);

        $usersCount = $role->users()->count();

        if ($usersCount > 0) {
            abort(403, __('Role masih digunakan oleh :count pengguna.', ['count' => $usersCount]));
        }

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role berhasil dihapus.')]);

        return to_route('roles.index');
    }

    /**
     * Ensure the built-in superadmin role stays untouched.
     */
    private function ensureRoleIsManageable(Role $role): void
    {
        if ($role->name === UserRole::SuperAdmin->value) {
            abort(403, __('Role superadmin tidak dapat dikelola.'));
        }
    }
}
