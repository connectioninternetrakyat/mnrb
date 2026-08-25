<?php

use App\Enums\UserRole;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\PppProfile;
use App\Models\User;
use Database\Seeders\SuperSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(SuperSeeder::class);
});

function packageActor(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));
}

function defaultMikrotikServer(): MikrotikServer
{
    return MikrotikServer::create([
        'name' => 'RTR-Uji',
        'host' => '127.0.0.1',
        'port' => 1,
        'username' => 'admin',
        'password' => 'x',
        'is_default' => true,
        'timeout' => 1,
    ]);
}

function profileForPackage(MikrotikServer $server, string $name = 'PPPoE-10M', string $rate = '10M/10M'): PppProfile
{
    return PppProfile::create([
        'mikrotik_server_id' => $server->id,
        'name' => $name,
        'rate_limit' => $rate,
    ]);
}

test('guests are redirected to login', function () {
    $this->get(route('packages.index'))->assertRedirect(route('login'));
});

test('users without permission cannot open the packages page', function () {
    $actor = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::Teknisi->value));

    $this->actingAs($actor)->get(route('packages.index'))->assertForbidden();
});

test('users without create permission cannot store packages', function () {
    $actor = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::Admin->value));

    $this->actingAs($actor)
        ->post(route('packages.store'), ['name' => 'Paket Uji'])
        ->assertForbidden();
});

test('superadmin can view packages page with real profile options', function () {
    $actor = packageActor();
    $server = defaultMikrotikServer();
    $profile = profileForPackage($server);

    $this->actingAs($actor)
        ->get(route('packages.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('packages/index')
                ->has('packages.data', 0)
                ->has('profiles', 1)
                ->where('profiles.0.id', $profile->id)
                ->where('profiles.0.name', 'PPPoE-10M')
                ->where('profiles.0.rate_limit', '10M/10M')
                ->where('profiles.0.has_package', false)
        );
});

test('package store requires name, profile and price', function () {
    $this->actingAs(packageActor());

    $this->post(route('packages.store'), [])
        ->assertSessionHasErrors(['name', 'ppp_profile_id', 'price']);
});

test('package store rejects profiles that do not exist', function () {
    $this->actingAs(packageActor());

    $this->post(route('packages.store'), [
        'name' => 'Paket Uji',
        'ppp_profile_id' => 99999,
        'price' => 150000,
    ])->assertSessionHasErrors(['ppp_profile_id']);
});

test('package store creates a package bound to a real ppp profile', function () {
    $actor = packageActor();
    $profile = profileForPackage(defaultMikrotikServer());

    $this->actingAs($actor)->post(route('packages.store'), [
        'name' => 'Paket Home 10M',
        'ppp_profile_id' => $profile->id,
        'price' => 150000,
        'description' => 'Unlimited 10 Mbps',
    ]);

    $package = Package::query()->sole();

    expect($package->name)->toBe('Paket Home 10M');
    expect($package->price)->toBe(150000);
    expect($package->description)->toBe('Unlimited 10 Mbps');
    expect($package->pppProfile->id)->toBe($profile->id);
    expect($package->pppProfile->rate_limit)->toBe('10M/10M');
});

test('one profile can only be used by one package', function () {
    $actor = packageActor();
    $profile = profileForPackage(defaultMikrotikServer());

    Package::create([
        'name' => 'Paket Pertama',
        'ppp_profile_id' => $profile->id,
        'price' => 100000,
    ]);

    $this->actingAs($actor)->post(route('packages.store'), [
        'name' => 'Paket Duplikat',
        'ppp_profile_id' => $profile->id,
        'price' => 200000,
    ])->assertSessionHasErrors(['ppp_profile_id']);
});

test('package update can rebind to another free profile', function () {
    $actor = packageActor();
    $server = defaultMikrotikServer();

    $first = profileForPackage($server, 'PPPoE-10M');
    $second = profileForPackage($server, 'PPPoE-20M', '20M/20M');

    $package = Package::create([
        'name' => 'Paket Home 10M',
        'ppp_profile_id' => $first->id,
        'price' => 150000,
    ]);

    $this->actingAs($actor)->put(route('packages.update', $package), [
        'name' => 'Paket Home 20M',
        'ppp_profile_id' => $second->id,
        'price' => 250000,
        'description' => null,
    ]);

    $package->refresh();

    expect($package->name)->toBe('Paket Home 20M');
    expect($package->price)->toBe(250000);
    expect($package->ppp_profile_id)->toBe($second->id);
});

test('deleting a package keeps the linked ppp profile intact', function () {
    $actor = packageActor();
    $profile = profileForPackage(defaultMikrotikServer());

    $package = Package::create([
        'name' => 'Paket Home 10M',
        'ppp_profile_id' => $profile->id,
        'price' => 150000,
    ]);

    $this->actingAs($actor)->delete(route('packages.destroy', $package));

    expect(Package::count())->toBe(0);
    expect(PppProfile::query()->whereKey($profile->id)->exists())->toBeTrue();
});

test('package index paginates ten per page and searches by name or profile', function () {
    $actor = packageActor();
    $server = defaultMikrotikServer();

    foreach (range(1, 12) as $i) {
        $profile = profileForPackage($server, sprintf('PPPoE-%02dM', $i));

        Package::create([
            'name' => sprintf('Paket-%02d', $i),
            'ppp_profile_id' => $profile->id,
            'price' => 10000 * $i,
        ]);
    }

    $this->actingAs($actor)
        ->get(route('packages.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('packages/index')
                ->has('packages.data', 10)
                ->where('packages.total', 12)
                ->where('packages.current_page', 1)
        );

    $this->actingAs($actor)
        ->get(route('packages.index', ['search' => 'Paket-11']))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('packages.data', 1)
                ->where('packages.data.0.name', 'Paket-11')
                ->where('packages.data.0.profile_name', 'PPPoE-11M')
        );
});
