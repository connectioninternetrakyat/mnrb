<?php

namespace Database\Seeders;

use App\Enums\MenuPermission;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SuperSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's roles, permissions and the superadmin account.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::updateOrCreate(
                [
                    'name' => $role->value,
                    'guard_name' => 'web',
                ],
                [
                    'description' => $role->description(),
                ],
            );
        }

        foreach (UserPermission::values() as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach (MenuPermission::values() as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach (UserRole::cases() as $role) {
            $roleModel = Role::where('name', $role->value)->firstOrFail();
            $roleModel->syncPermissions([
                ...UserPermission::forRole($role),
                ...MenuPermission::forRole($role),
            ]);
        }

        $superadmin = User::firstOrCreate(
            [
                'email' => 'dev@mnrb.com',
            ],
            [
                'name' => 'Developer',
                'password' => '2Sukses2!',
            ],
        );

        $superadmin->syncRoles([UserRole::SuperAdmin->value]);
    }
}
