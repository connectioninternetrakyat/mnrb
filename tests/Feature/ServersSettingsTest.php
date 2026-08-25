<?php

use App\Enums\UserRole;
use App\Models\GenieacsServer;
use App\Models\MikrotikServer;
use App\Models\RadiusServer;
use App\Models\User;
use Database\Seeders\SuperSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(SuperSeeder::class);
});

function serverActor(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));
}

test('guests are redirected to login', function () {
    $this->get(route('servers.mikrotik.index'))->assertRedirect(route('login'));
    $this->get(route('servers.radius.index'))->assertRedirect(route('login'));
    $this->get(route('servers.genieacs.index'))->assertRedirect(route('login'));
});

test('users without permission cannot open server pages', function () {
    $actor = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::Teknisi->value));

    $this->actingAs($actor)->get(route('servers.mikrotik.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('servers.radius.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('servers.genieacs.index'))->assertForbidden();
});

test('superadmin can view all server lists', function () {
    MikrotikServer::create(['name' => 'RTR-1', 'host' => '10.0.0.1', 'username' => 'admin']);
    RadiusServer::create(['name' => 'RAD-1', 'server_host' => '10.0.0.2']);
    GenieacsServer::create(['name' => 'ACS-1', 'base_url' => 'http://acs.test']);

    $actor = serverActor();

    $this->actingAs($actor)
        ->get(route('servers.mikrotik.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('servers/mikrotik')
                ->has('servers', 1)
                ->where('servers.0.name', 'RTR-1')
        );

    $this->actingAs($actor)
        ->get(route('servers.radius.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('servers/radius')
                ->has('servers', 1)
                ->has('drivers', 2)
        );

    $this->actingAs($actor)
        ->get(route('servers.genieacs.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('servers/genieacs')
                ->has('servers', 1)
                ->where('servers.0.name', 'ACS-1')
        );
});

test('mikrotik servers support full crud with encrypted password', function () {
    $actor = serverActor();

    $this->actingAs($actor)
        ->post(route('servers.mikrotik.store'), [
            'name' => 'Router Pusat',
            'host' => '192.168.88.1',
            'port' => '8729',
            'username' => 'api-admin',
            'password' => 'router-secret',
            'use_tls' => '1',
            'timeout' => '10',
        ])
        ->assertRedirect();

    $server = MikrotikServer::firstOrFail();

    expect($server->password)->toBe('router-secret');
    expect(DB::table('mikrotik_servers')->value('password'))->not->toBe('router-secret');

    $this->actingAs($actor)
        ->put(route('servers.mikrotik.update', $server), [
            'name' => 'Router Cabang',
            'host' => '192.168.88.2',
            'port' => '8728',
            'username' => 'admin',
            'password' => '',
            'use_tls' => '0',
            'timeout' => '5',
        ])
        ->assertRedirect();

    $server->refresh();

    expect($server->name)->toBe('Router Cabang');
    expect($server->password)->toBe('router-secret');

    $this->actingAs($actor)
        ->delete(route('servers.mikrotik.destroy', $server))
        ->assertRedirect();

    expect(MikrotikServer::count())->toBe(0);
});

test('radius servers support full crud for both drivers with encrypted secrets', function () {
    $actor = serverActor();

    foreach (['mysql', 'pgsql'] as $driver) {
        $this->actingAs($actor)
            ->post(route('servers.radius.store'), [
                'name' => "RAD-$driver",
                'server_host' => '10.10.10.5',
                'auth_port' => '1812',
                'shared_secret' => "secret-$driver",
                'timeout' => '5',
                'db_driver' => $driver,
                'db_host' => '127.0.0.1',
                'db_database' => 'radius',
                'db_username' => 'radius',
                'db_password' => "db-pass-$driver",
            ])
            ->assertRedirect();
    }

    expect(RadiusServer::count())->toBe(2);

    $server = RadiusServer::where('db_driver', 'pgsql')->firstOrFail();
    expect($server->shared_secret)->toBe('secret-pgsql');
    expect(DB::table('radius_servers')->where('id', $server->id)->value('shared_secret'))
        ->not->toBe('secret-pgsql');

    $this->actingAs($actor)
        ->put(route('servers.radius.update', $server), [
            'name' => 'RAD-UPDATED',
            'server_host' => '10.10.10.9',
            'auth_port' => '1812',
            'shared_secret' => '',
            'timeout' => '5',
            'db_driver' => 'pgsql',
            'db_host' => '127.0.0.1',
            'db_database' => 'radius',
            'db_username' => 'radius',
            'db_password' => '',
        ])
        ->assertRedirect();

    $server->refresh();

    expect($server->name)->toBe('RAD-UPDATED');
    expect($server->shared_secret)->toBe('secret-pgsql');
    expect($server->db_password)->toBe('db-pass-pgsql');

    $this->actingAs($actor)
        ->delete(route('servers.radius.destroy', $server))
        ->assertRedirect();

    expect(RadiusServer::count())->toBe(1);
});

test('radius rejects an unsupported database driver', function () {
    $actor = serverActor();

    $this->actingAs($actor)
        ->post(route('servers.radius.store'), [
            'name' => 'BAD',
            'server_host' => '10.10.10.5',
            'auth_port' => '1812',
            'timeout' => '5',
            'db_driver' => 'oracle',
        ])
        ->assertSessionHasErrors(['db_driver']);
});

test('genieacs servers support full crud with encrypted password', function () {
    $actor = serverActor();

    $this->actingAs($actor)
        ->post(route('servers.genieacs.store'), [
            'name' => 'ACS Utama',
            'base_url' => 'http://acs.test:7557',
            'username' => 'admin',
            'password' => 'acs-secret',
        ])
        ->assertRedirect();

    $server = GenieacsServer::firstOrFail();
    expect($server->password)->toBe('acs-secret');
    expect(DB::table('genieacs_servers')->value('password'))->not->toBe('acs-secret');

    $this->actingAs($actor)
        ->put(route('servers.genieacs.update', $server), [
            'name' => 'ACS Cadangan',
            'base_url' => 'http://acs2.test:7557',
            'username' => 'admin',
            'password' => '',
        ])
        ->assertRedirect();

    expect($server->refresh()->password)->toBe('acs-secret');

    $this->actingAs($actor)
        ->delete(route('servers.genieacs.destroy', $server))
        ->assertRedirect();

    expect(GenieacsServer::count())->toBe(0);
});

test('mikrotik test connection marks failure gracefully when router is unreachable', function () {
    $actor = serverActor();
    $server = MikrotikServer::create([
        'name' => 'RTR',
        'host' => '127.0.0.1',
        'port' => 1,
        'username' => 'admin',
        'timeout' => 1,
    ]);

    $this->actingAs($actor)
        ->post(route('servers.mikrotik.test', $server))
        ->assertRedirect();

    $server->refresh();

    expect($server->is_connected)->toBeFalse();
    expect($server->last_checked_at)->not->toBeNull();
    expect($server->last_error)->not->toBeNull();
});

test('mikrotik detail renders the list page and marks failure gracefully', function () {
    $actor = serverActor();
    $server = MikrotikServer::create([
        'name' => 'RTR',
        'host' => '127.0.0.1',
        'port' => 1,
        'username' => 'admin',
        'timeout' => 1,
    ]);

    $this->actingAs($actor)
        ->get(route('servers.mikrotik.detail', $server))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('servers/mikrotik')
                ->where('detail', null)
        );

    expect($server->refresh()->is_connected)->toBeFalse();
});

test('radius authentication test marks failure gracefully when server is unreachable', function () {
    $actor = serverActor();
    $server = RadiusServer::create([
        'name' => 'RAD',
        'server_host' => '127.0.0.1',
        'auth_port' => 18121,
        'shared_secret' => 'testing123',
        'timeout' => 1,
    ]);

    $this->actingAs($actor)
        ->post(route('servers.radius.test-radius', $server), [
            'test_username' => 'u',
            'test_password' => 'p',
        ])
        ->assertRedirect();

    $server->refresh();

    expect($server->is_connected)->toBeFalse();
    expect($server->last_error)->not->toBeNull();
});

test('radius database test supports mysql and postgresql drivers gracefully', function () {
    $actor = serverActor();

    foreach (['mysql', 'pgsql'] as $driver) {
        $server = RadiusServer::create([
            'name' => "RAD-$driver",
            'server_host' => '127.0.0.1',
            'db_driver' => $driver,
            'db_host' => '127.0.0.1',
            'db_port' => 1,
            'db_database' => 'radius',
            'db_username' => 'radius',
            'timeout' => 1,
        ]);

        $this->actingAs($actor)
            ->post(route('servers.radius.test-database', $server))
            ->assertRedirect();

        $server->refresh();

        expect($server->is_connected)->toBeFalse();
        expect($server->last_error)->not->toBeNull();

        $server->delete();
    }
});

test('genieacs test connection succeeds when nbi responds', function () {
    Http::fake([
        'acs.test/devices*' => Http::response([['_id' => 'd1']], 200),
    ]);

    $actor = serverActor();
    $server = GenieacsServer::create(['name' => 'ACS', 'base_url' => 'http://acs.test']);

    $this->actingAs($actor)
        ->post(route('servers.genieacs.test', $server))
        ->assertRedirect();

    expect($server->refresh()->is_connected)->toBeTrue();
});

test('genieacs test connection marks failure on http error', function () {
    Http::fake([
        'acs.test/devices*' => Http::response('error', 500),
    ]);

    $actor = serverActor();
    $server = GenieacsServer::create(['name' => 'ACS', 'base_url' => 'http://acs.test']);

    $this->actingAs($actor)
        ->post(route('servers.genieacs.test', $server))
        ->assertRedirect();

    $server->refresh();

    expect($server->is_connected)->toBeFalse();
    expect($server->last_error)->not->toBeNull();
});

test('genieacs detail shows a device summary from the nbi api', function () {
    Http::fake([
        'acs.test/devices' => Http::response([
            [
                '_id' => 'device-1',
                '_lastInform' => '2026-08-22T03:00:00.000Z',
                'DeviceID' => [
                    'Manufacturer' => 'TP-Link',
                    'ProductClass' => 'EC230-G1',
                    'SerialNumber' => 'SN-001',
                ],
            ],
        ], 200),
    ]);

    $actor = serverActor();
    $server = GenieacsServer::create([
        'name' => 'ACS',
        'base_url' => 'http://acs.test',
        'username' => 'admin',
        'password' => 'secret',
    ]);

    $this->actingAs($actor)
        ->get(route('servers.genieacs.detail', $server))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('servers/genieacs')
                ->where('detail.total_devices', 1)
                ->where('detail.devices.0.product_class', 'EC230-G1')
        );

    expect($server->refresh()->is_connected)->toBeTrue();
});
