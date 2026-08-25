<?php

namespace App\Http\Controllers;

use App\Enums\MenuPermission;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Http\Requests\Roles\SyncRolePermissionsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    /**
     * Show the menu permission matrix for the selected role.
     */
    public function index(): Response
    {
        Gate::authorize(UserPermission::RolesView->value);

        $roles = Role::query()
            ->where('name', '!=', UserRole::SuperAdmin->value)
            ->orderBy('name')
            ->get(['id', 'name']);

        $selected = $roles->firstWhere('name', request('role')) ?? $roles->first();

        return Inertia::render('permissions/index', [
            'roles' => $roles->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
            ])->all(),
            'menus' => MenuPermission::options(),
            'filters' => [
                'role' => request('role'),
            ],
            'selectedRole' => $selected === null ? null : [
                'id' => $selected->id,
                'name' => $selected->name,
                'menus' => collect($selected->permissions->pluck('name'))
                    ->filter(fn (string $name): bool => MenuPermission::isMenuPermission($name))
                    ->values()
                    ->all(),
            ],
        ]);
    }

    /**
     * Sync the sidebar menu permissions of the given role.
     */
    public function update(SyncRolePermissionsRequest $request, Role $role): RedirectResponse
    {
        if ($role->name === UserRole::SuperAdmin->value) {
            abort(403, __('Menu untuk role superadmin tidak dapat diubah.'));
        }

        $menuNames = $request->validated('menus');

        // Keep non-menu (system) permissions untouched while syncing menus.
        $systemPermissions = $role->permissions
            ->pluck('name')
            ->reject(fn (string $name): bool => MenuPermission::isMenuPermission($name))
            ->values()
            ->all();

        $role->syncPermissions(array_merge($menuNames, $systemPermissions));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission role :name berhasil diperbarui.', ['name' => $role->name])]);

        return to_route('permissions.index', ['role' => $role->name]);
    }
}
