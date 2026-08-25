<?php

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\SuperSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(SuperSeeder::class);

    Storage::fake('public');
});

function actorWithRole(string $role): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole($role));
}

test('guests are redirected to login', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

test('users without permission cannot access user management', function (string $role) {
    $actor = actorWithRole($role);

    $this->actingAs($actor)->get(route('users.index'))->assertForbidden();
})->with([
    'noc' => UserRole::Noc->value,
    'teknisi' => UserRole::Teknisi->value,
    'customer service' => UserRole::CustomerService->value,
    'marketing' => UserRole::Marketing->value,
]);

test('admin and superadmin can view the users index', function (string $role) {
    $actor = actorWithRole($role);

    $this->actingAs($actor)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('users/index')
                ->has('users.data')
        );
})->with([
    'superadmin' => UserRole::SuperAdmin->value,
    'admin' => UserRole::Admin->value,
]);

test('index search filters by name or email', function () {
    $actor = actorWithRole(UserRole::SuperAdmin->value);
    User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@mnrb.com']);
    User::factory()->create(['name' => 'Citra Lestari', 'email' => 'citra@mnrb.com']);

    $this->actingAs($actor)
        ->get(route('users.index', ['search' => 'budi']))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('users/index')
                ->has('users.data', 1)
                ->where('users.data.0.email', 'budi@mnrb.com')
        );
});

test('index filters by role', function () {
    $actor = actorWithRole(UserRole::SuperAdmin->value);
    $teknisi = User::factory()->create();
    $teknisi->assignRole(UserRole::Teknisi->value);
    $marketing = User::factory()->create();
    $marketing->assignRole(UserRole::Marketing->value);

    $this->actingAs($actor)
        ->get(route('users.index', ['role' => UserRole::Marketing->value]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('users/index')
                ->has('users.data', 1)
                ->where('users.data.0.id', $marketing->id)
        );
});

test('superadmin can create a user with a role', function () {
    $actor = actorWithRole(UserRole::SuperAdmin->value);

    $response = $this->actingAs($actor)
        ->post(route('users.store'), [
            'name' => 'Budi Teknisi',
            'email' => 'budi@mnrb.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'whatsapp' => '+6281234567890',
            'role' => UserRole::Teknisi->value,
        ]);

    $response->assertRedirect();

    $user = User::where('email', 'budi@mnrb.com')->firstOrFail();

    expect($user->name)->toBe('Budi Teknisi');
    expect($user->whatsapp)->toBe('+6281234567890');
    expect($user->hasRole(UserRole::Teknisi->value))->toBeTrue();
});

test('superadmin can create a user with an avatar', function () {
    $actor = actorWithRole(UserRole::SuperAdmin->value);

    $this->actingAs($actor)
        ->post(route('users.store'), [
            'name' => 'Foto User',
            'email' => 'foto@mnrb.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'avatar' => UploadedFile::fake()->image('avatar.png'),
            'role' => UserRole::Admin->value,
        ])
        ->assertRedirect();

    $user = User::where('email', 'foto@mnrb.com')->firstOrFail();

    expect($user->avatar)->not->toBeNull();
    Storage::disk('public')->assertExists($user->avatar);
});

test('store requires a unique email, password confirmation and valid role', function () {
    $actor = actorWithRole(UserRole::SuperAdmin->value);
    $existing = User::factory()->create();

    $this->actingAs($actor)
        ->post(route('users.store'), [
            'name' => 'Duplikat',
            'email' => $existing->email,
            'password' => 'rahasia123',
            'password_confirmation' => 'beda',
            'role' => 'peran-tidak-ada',
        ])
        ->assertSessionHasErrors(['email', 'password', 'role']);
});

test('admin cannot assign the superadmin role', function () {
    $actor = actorWithRole(UserRole::Admin->value);

    $this->actingAs($actor)
        ->post(route('users.store'), [
            'name' => 'Jadi Superadmin',
            'email' => 'escalate@mnrb.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
            'role' => UserRole::SuperAdmin->value,
        ])
        ->assertSessionHasErrors('role');

    expect(User::where('email', 'escalate@mnrb.com')->exists())->toBeFalse();
});

test('admin cannot edit or delete a superadmin user', function () {
    $admin = actorWithRole(UserRole::Admin->value);
    $superadmin = User::where('email', 'dev@mnrb.com')->firstOrFail();

    $this->actingAs($admin)
        ->put(route('users.update', $superadmin), [
            'name' => $superadmin->name,
            'email' => $superadmin->email,
            'role' => UserRole::Teknisi->value,
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $superadmin))
        ->assertForbidden();
});

test('admin can update basic fields of a regular user but not assign superadmin role', function () {
    $admin = actorWithRole(UserRole::Admin->value);
    $target = actorWithRole(UserRole::Teknisi->value);

    $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => 'Nama Baru',
            'email' => $target->email,
            'whatsapp' => '081298765432',
            'role' => UserRole::CustomerService->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $target->refresh();

    expect($target->name)->toBe('Nama Baru');
    expect($target->hasRole(UserRole::CustomerService->value))->toBeTrue();

    $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => UserRole::SuperAdmin->value,
        ])
        ->assertSessionHasErrors('role');
});

test('update keeps password unchanged when left blank and sets new password when provided', function () {
    $superadmin = actorWithRole(UserRole::SuperAdmin->value);
    $target = actorWithRole(UserRole::Teknisi->value);
    $oldPasswordHash = $target->password;

    $this->actingAs($superadmin)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => UserRole::Teknisi->value,
            'password' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($target->refresh()->password)->toBe($oldPasswordHash);

    $this->actingAs($superadmin)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role' => UserRole::Teknisi->value,
            'password' => 'password-baru-456',
            'password_confirmation' => 'password-baru-456',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('password-baru-456', $target->refresh()->password))->toBeTrue();
});

test('the last active superadmin role cannot be revoked', function () {
    $lastSuperadmin = User::where('email', 'dev@mnrb.com')->firstOrFail();

    $this->actingAs($lastSuperadmin)
        ->put(route('users.update', $lastSuperadmin), [
            'name' => $lastSuperadmin->name,
            'email' => $lastSuperadmin->email,
            'role' => UserRole::Admin->value,
        ])
        ->assertSessionHasErrors('role');

    expect($lastSuperadmin->fresh()->hasRole(UserRole::SuperAdmin->value))->toBeTrue();
});

test('a superadmin can delete another superadmin while one remains', function () {
    $actor = actorWithRole(UserRole::SuperAdmin->value);
    $lastSuperadmin = User::where('email', 'dev@mnrb.com')->firstOrFail();

    $this->actingAs($actor)
        ->delete(route('users.destroy', $actor))
        ->assertForbidden();

    $this->actingAs($actor)
        ->delete(route('users.destroy', $lastSuperadmin))
        ->assertRedirect(route('users.index'));

    expect(User::role(UserRole::SuperAdmin->value)->count())->toBe(1);
});

test('users cannot delete their own account from user management', function () {
    $actor = actorWithRole(UserRole::SuperAdmin->value);

    $this->actingAs($actor)
        ->delete(route('users.destroy', $actor))
        ->assertForbidden();

    expect($actor->fresh())->not->toBeNull();
});

test('superadmin can delete another user', function () {
    $actor = actorWithRole(UserRole::SuperAdmin->value);
    $target = actorWithRole(UserRole::Marketing->value);

    $this->actingAs($actor)
        ->from(route('users.index'))
        ->delete(route('users.destroy', $target))
        ->assertRedirect(route('users.index'));

    expect(User::find($target->id))->toBeNull();
});

test('admins cannot delete users because they lack the delete permission', function () {
    $admin = actorWithRole(UserRole::Admin->value);
    $target = actorWithRole(UserRole::Teknisi->value);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $target))
        ->assertForbidden();

    expect($target->fresh())->not->toBeNull();
});

test('deleting a user with an avatar removes the stored file', function () {
    Storage::fake('public');

    $actor = actorWithRole(UserRole::SuperAdmin->value);
    $target = actorWithRole(UserRole::Teknisi->value);
    $path = UploadedFile::fake()->image('hapus.png')->store('avatars', 'public');
    $target->update(['avatar' => $path]);

    $this->actingAs($actor)
        ->delete(route('users.destroy', $target))
        ->assertRedirect();

    Storage::disk('public')->assertMissing($path);
});

test('all permissions exist for every seeded role matrix entry', function () {
    foreach ([UserRole::SuperAdmin, UserRole::Admin, UserRole::Marketing, UserRole::Teknisi] as $role) {
        $permissions = UserPermission::forRole($role);

        foreach ($permissions as $permission) {
            expect(Permission::where('name', $permission)->exists())->toBeTrue();
        }
    }

    expect(UserPermission::values())->toHaveCount(36);
});
