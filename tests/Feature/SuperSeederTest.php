<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\SuperSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

test('super seeder creates all roles', function () {
    $this->seed(SuperSeeder::class);

    foreach (UserRole::cases() as $role) {
        expect(Role::where('name', $role->value)->exists())->toBeTrue();
    }

    expect(Role::count())->toBe(count(UserRole::cases()));
});

test('super seeder creates the superadmin account with the superadmin role', function () {
    $this->seed(SuperSeeder::class);

    $user = User::where('email', 'dev@mnrb.com')->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Developer');
    expect(Hash::check('2Sukses2!', $user->password))->toBeTrue();
    expect($user->hasRole(UserRole::SuperAdmin->value))->toBeTrue();
});

test('superadmin user can authenticate with the seeded credentials', function () {
    $this->seed(SuperSeeder::class);

    $response = $this->post(route('login.store'), [
        'email' => 'dev@mnrb.com',
        'password' => '2Sukses2!',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs(User::where('email', 'dev@mnrb.com')->first());
});
