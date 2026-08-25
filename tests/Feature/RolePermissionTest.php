<?php

use App\Enums\MenuPermission;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\SuperSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(SuperSeeder::class);
});

function actorWithPermissions(string ...$permissions): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->givePermissionTo($permissions));
}

test('guests are redirected to login', function () {
    $this->get(route('permissions.index'))->assertRedirect(route('login'));
});

test('users without permission cannot access the permission page', function () {
    $actor = User::factory()->create();
    $actor->assignRole(UserRole::Teknisi->value);

    $this->actingAs($actor)->get(route('permissions.index'))->assertForbidden();
});

test('superadmin can view menu permissions of a role', function () {
    $actor = actorWithPermissions(UserPermission::RolesView->value);

    $this->actingAs($actor)
        ->get(route('permissions.index', ['role' => UserRole::Admin->value]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('permissions/index')
                ->where('selectedRole.name', UserRole::Admin->value)
                ->where('selectedRole.menus', [
                    MenuPermission::Users->value,
                    MenuPermission::Pelanggans->value,
                    MenuPermission::Prospects->value,
                    MenuPermission::Tickets->value,
                    MenuPermission::Billing->value,
                    MenuPermission::BillingSetting->value,
                    MenuPermission::BillingBills->value,
                    MenuPermission::BillingReports->value,
                    MenuPermission::BillingCustomers->value,
                    MenuPermission::Whatsapp->value,
                    MenuPermission::WaTemplates->value,
                    MenuPermission::WaBroadcast->value,
                ])
                ->has('menus', count(MenuPermission::cases()))
        );
});

test('roles without assigned permissions default to the first role', function () {
    $actor = actorWithPermissions(UserPermission::RolesView->value);

    $this->actingAs($actor)
        ->get(route('permissions.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('permissions/index')
                ->where('selectedRole.name', UserRole::Admin->value)
        );
});

test('the superadmin role is not listed on the permission page', function () {
    $actor = actorWithPermissions(UserPermission::RolesView->value);

    $this->actingAs($actor)
        ->get(route('permissions.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('permissions/index')
                ->has('roles', count(UserRole::cases()) - 1)
                ->where('roles.0.name', UserRole::Admin->value)
        );
});

test('superadmin can sync menu permissions for a role', function () {
    $actor = actorWithPermissions(UserPermission::RolesUpdate->value);
    $role = Role::where('name', UserRole::Teknisi->value)->firstOrFail();

    $this->actingAs($actor)
        ->put(route('permissions.update', $role), [
            'menus' => [MenuPermission::Users->value],
        ])
        ->assertRedirect(route('permissions.index', ['role' => UserRole::Teknisi->value]));

    $role->refresh();

    expect($role->hasPermissionTo(MenuPermission::Users->value))->toBeTrue();
});

test('syncing menus keeps system permissions intact', function () {
    $actor = actorWithPermissions(UserPermission::RolesUpdate->value);
    $role = Role::where('name', UserRole::Admin->value)->firstOrFail();
    $existingSystemPermission = $role->permissions
        ->pluck('name')
        ->first(fn (string $name): bool => ! MenuPermission::isMenuPermission($name));

    $this->actingAs($actor)
        ->put(route('permissions.update', $role), ['menus' => []])
        ->assertRedirect();

    $role->refresh();

    expect($role->hasPermissionTo(MenuPermission::Users->value))->toBeFalse();
    expect($role->hasPermissionTo($existingSystemPermission))->toBeTrue();
});

test('menu values must be valid', function () {
    $actor = actorWithPermissions(UserPermission::RolesUpdate->value);
    $role = Role::where('name', UserRole::Teknisi->value)->firstOrFail();

    $this->actingAs($actor)
        ->put(route('permissions.update', $role), ['menus' => ['menus.tidak-ada']])
        ->assertSessionHasErrors(['menus.0']);
});

test('the superadmin role permissions cannot be changed via the permission page', function () {
    $actor = actorWithPermissions(UserPermission::RolesView->value, UserPermission::RolesUpdate->value);
    $role = Role::where('name', UserRole::SuperAdmin->value)->firstOrFail();

    $this->actingAs($actor)
        ->put(route('permissions.update', $role), ['menus' => []])
        ->assertForbidden();

    $role->refresh();

    expect($role->hasPermissionTo(MenuPermission::Users->value))->toBeTrue();
});

test('actors without update permission cannot sync menus', function () {
    $actor = actorWithPermissions(UserPermission::RolesView->value);
    $role = Role::where('name', UserRole::Teknisi->value)->firstOrFail();

    $this->actingAs($actor)
        ->put(route('permissions.update', $role), ['menus' => []])
        ->assertForbidden();
});
