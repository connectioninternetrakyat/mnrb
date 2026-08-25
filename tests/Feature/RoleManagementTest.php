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

function actorWithRoles(string ...$roles): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole($roles));
}

test('guests are redirected to login', function () {
    $this->get(route('roles.index'))->assertRedirect(route('login'));
});

test('users without permission cannot access role management', function (string $role) {
    $actor = actorWithRoles($role);

    $this->actingAs($actor)->get(route('roles.index'))->assertForbidden();
})->with([
    'noc' => UserRole::Noc->value,
    'teknisi' => UserRole::Teknisi->value,
]);

test('superadmin can view the roles index', function () {
    $actor = actorWithRoles(UserRole::SuperAdmin->value);

    $this->actingAs($actor)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('roles/index')
                ->has('roles', count(UserRole::cases()))
                ->where('roles.0.name', UserRole::Admin->value)
        );
});

test('the standalone create role page is no longer exposed', function () {
    $actor = actorWithRoles(UserRole::SuperAdmin->value);

    $this->actingAs($actor)->get('/roles/create')->assertStatus(405);
});

test('superadmin can create a role', function () {
    $actor = actorWithRoles(UserRole::SuperAdmin->value);

    $this->actingAs($actor)
        ->post(route('roles.store'), [
            'name' => 'supervisor lapangan',
            'description' => 'Mengawasi pekerjaan teknisi di lapangan.',
        ])
        ->assertRedirect(route('roles.index'));

    $role = Role::where('name', 'supervisor lapangan')->firstOrFail();

    expect($role->description)->toBe('Mengawasi pekerjaan teknisi di lapangan.');
});

test('store validates name uniqueness and description length', function () {
    $actor = actorWithRoles(UserRole::SuperAdmin->value);

    $this->actingAs($actor)
        ->post(route('roles.store'), [
            'name' => UserRole::Teknisi->value,
            'description' => str_repeat('a', 501),
        ])
        ->assertSessionHasErrors(['name', 'description']);
});

test('newly created roles are assignable to users', function () {
    $actor = actorWithRoles(UserRole::SuperAdmin->value);

    $this->actingAs($actor)
        ->post(route('roles.store'), ['name' => 'qa engineer'])
        ->assertRedirect();

    $this->actingAs($actor)
        ->post(route('users.store'), [
            'name' => 'QA Engineer',
            'email' => 'qa@mnrb.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'role' => 'qa engineer',
        ])
        ->assertRedirect();

    $user = User::where('email', 'qa@mnrb.com')->firstOrFail();

    expect($user->hasRole('qa engineer'))->toBeTrue();
});

test('superadmin can rename a role', function () {
    $actor = actorWithRoles(UserRole::SuperAdmin->value);
    $role = Role::where('name', UserRole::Marketing->value)->firstOrFail();

    $this->actingAs($actor)
        ->put(route('roles.update', $role), ['name' => 'sales marketing'])
        ->assertRedirect(route('roles.index'));

    expect(Role::where('name', 'sales marketing')->exists())->toBeTrue();
    expect(Role::where('name', UserRole::Marketing->value)->exists())->toBeFalse();
});

test('the superadmin role cannot be renamed or deleted', function () {
    $actor = actorWithRoles(UserRole::SuperAdmin->value);
    $role = Role::where('name', UserRole::SuperAdmin->value)->firstOrFail();

    $this->actingAs($actor)
        ->put(route('roles.update', $role), ['name' => 'root'])
        ->assertForbidden();

    $this->actingAs($actor)
        ->delete(route('roles.destroy', $role))
        ->assertForbidden();

    expect(Role::where('name', UserRole::SuperAdmin->value)->exists())->toBeTrue();
});

test('a role that still has users cannot be deleted', function () {
    $actor = actorWithRoles(UserRole::SuperAdmin->value);
    $target = actorWithRoles(UserRole::Teknisi->value);
    $role = Role::where('name', UserRole::Teknisi->value)->firstOrFail();

    $this->actingAs($actor)
        ->delete(route('roles.destroy', $role))
        ->assertForbidden();

    expect($target->fresh())->not->toBeNull();
    expect(Role::where('name', UserRole::Teknisi->value)->exists())->toBeTrue();
});

test('an unused role can be deleted', function () {
    $actor = actorWithRoles(UserRole::SuperAdmin->value);
    $role = Role::create(['name' => 'magang', 'guard_name' => 'web']);

    $this->actingAs($actor)
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'));

    expect(Role::where('name', 'magang')->exists())->toBeFalse();
});

test('actors with only the update permission cannot rename the superadmin role', function () {
    $actor = tap(
        User::factory()->create(),
        fn (User $user) => $user->givePermissionTo(UserPermission::RolesUpdate->value),
    );
    $role = Role::where('name', UserRole::SuperAdmin->value)->firstOrFail();

    $this->actingAs($actor)
        ->put(route('roles.update', $role), ['name' => 'root'])
        ->assertForbidden();
});

test('default menu permissions follow the seeded matrix', function () {
    $superadmin = Role::where('name', UserRole::SuperAdmin->value)->firstOrFail();
    $admin = Role::where('name', UserRole::Admin->value)->firstOrFail();
    $teknisi = Role::where('name', UserRole::Teknisi->value)->firstOrFail();

    expect($superadmin->permissions->pluck('name')->toArray())
        ->toContain(...MenuPermission::values());

    expect($admin->hasPermissionTo(MenuPermission::Users->value))->toBeTrue();
    expect($admin->hasPermissionTo(MenuPermission::Roles->value))->toBeFalse();

    expect($teknisi->hasPermissionTo(MenuPermission::Users->value))->toBeFalse();
});
