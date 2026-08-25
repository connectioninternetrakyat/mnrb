<?php

use App\Enums\UserRole;
use App\Models\MidtransAccount;
use App\Models\User;
use App\Models\WahaServer;
use Database\Seeders\SuperSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(SuperSeeder::class);
});

function gatewayActor(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));
}

test('guests are redirected to login', function () {
    $this->get(route('gateways.midtrans.index'))->assertRedirect(route('login'));
    $this->get(route('gateways.waha.index'))->assertRedirect(route('login'));
});

test('users without permission cannot open gateway pages', function () {
    $actor = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::Teknisi->value));

    $this->actingAs($actor)->get(route('gateways.midtrans.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('gateways.waha.index'))->assertForbidden();
});

test('superadmin can view the gateway lists', function () {
    MidtransAccount::create(['name' => 'Utama', 'server_key' => 'sk-1']);
    WahaServer::create(['name' => 'WAHA 1', 'base_url' => 'https://waha.test']);

    $actor = gatewayActor();

    $this->actingAs($actor)
        ->get(route('gateways.midtrans.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('gateways/midtrans')
                ->has('accounts', 1)
                ->where('accounts.0.name', 'Utama')
                ->whereType('webhook_url', 'string')
        );

    $this->actingAs($actor)
        ->get(route('gateways.waha.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('gateways/waha')
                ->has('servers', 1)
                ->where('servers.0.base_url', 'https://waha.test')
        );
});

test('superadmin can create and update a midtrans account with encrypted server key', function () {
    $actor = gatewayActor();

    $this->actingAs($actor)
        ->post(route('gateways.midtrans.store'), [
            'name' => 'Midtrans Utama',
            'is_production' => '1',
            'merchant_id' => 'G123456789',
            'server_key' => 'Mid-server-secret',
            'client_key' => 'Mid-client',
        ])
        ->assertRedirect();

    $account = MidtransAccount::firstOrFail();

    expect($account->name)->toBe('Midtrans Utama');
    expect($account->is_production)->toBeTrue();
    expect($account->server_key)->toBe('Mid-server-secret');
    expect(DB::table('midtrans_accounts')->value('server_key'))->not->toBe('Mid-server-secret');

    $this->actingAs($actor)
        ->put(route('gateways.midtrans.update', $account), [
            'name' => 'Midtrans Cadangan',
            'is_production' => '0',
            'merchant_id' => 'G999',
            'server_key' => '',
        ])
        ->assertRedirect();

    $account->refresh();

    expect($account->name)->toBe('Midtrans Cadangan');
    expect($account->server_key)->toBe('Mid-server-secret');
});

test('superadmin can delete a midtrans account', function () {
    $actor = gatewayActor();
    $account = MidtransAccount::create(['name' => 'Hapus aku']);

    $this->actingAs($actor)
        ->delete(route('gateways.midtrans.destroy', $account))
        ->assertRedirect();

    expect(MidtransAccount::count())->toBe(0);
});

test('superadmin can create, update and delete a waha server with encrypted api key', function () {
    $actor = gatewayActor();

    $this->actingAs($actor)
        ->post(route('gateways.waha.store'), [
            'name' => 'WAHA Utama',
            'base_url' => 'https://waha.example.com',
            'api_key' => 'waha-secret',
            'session' => 'cs',
        ])
        ->assertRedirect();

    $server = WahaServer::firstOrFail();

    expect($server->api_key)->toBe('waha-secret');
    expect(DB::table('waha_servers')->value('api_key'))->not->toBe('waha-secret');

    $this->actingAs($actor)
        ->put(route('gateways.waha.update', $server), [
            'name' => 'WAHA Cadangan',
            'base_url' => 'https://waha2.example.com',
            'api_key' => '',
            'session' => 'default',
        ])
        ->assertRedirect();

    $server->refresh();

    expect($server->name)->toBe('WAHA Cadangan');
    expect($server->api_key)->toBe('waha-secret');

    $this->actingAs($actor)
        ->delete(route('gateways.waha.destroy', $server))
        ->assertRedirect();

    expect(WahaServer::count())->toBe(0);
});

test('qr endpoint returns data uri png from waha', function () {
    $actor = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));

    $server = WahaServer::create([
        'name' => 'WAHA QR',
        'base_url' => 'http://waha.test',
        'api_key' => 'secret',
        'session' => 'default',
        'is_connected' => false,
    ]);

    Http::fake([
        'waha.test/api/sessions/start' => Http::response(['name' => 'default', 'status' => 'STARTING']),
        'waha.test/api/sessions/default' => Http::response(['name' => 'default', 'status' => 'SCAN_QR_CODE']),
        'waha.test/api/default/auth/qr*' => Http::response(
            file_get_contents(__DIR__.'/../Fixtures/test.png') ?: 'PNGDATA',
            200,
            ['Content-Type' => 'image/png'],
        ),
    ]);

    $this->actingAs($actor)
        ->get(route('gateways.waha.qr', $server))
        ->assertOk()
        ->assertJsonPath('status', 'SCAN_QR_CODE')
        ->assertJsonPath('qr', fn ($qr) => str_starts_with((string) $qr, 'data:image/png;base64,'));
});

test('qr endpoint falls back to legacy waha routes', function () {
    $actor = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));

    $server = WahaServer::create([
        'name' => 'WAHA Legacy',
        'base_url' => 'http://waha-legacy.test',
        'api_key' => 'secret',
        'session' => 'default',
        'is_connected' => false,
    ]);

    Http::fake([
        'waha-legacy.test/api/sessions/default/stop' => Http::response(['message' => 'Not Found'], 404),
        'waha-legacy.test/api/session/stop' => Http::response(['message' => 'Not Found'], 404),
        'waha-legacy.test/api/sessions/start' => Http::response(['message' => 'Not Found'], 404),
        'waha-legacy.test/api/session/start' => Http::response(['success' => true]),
        'waha-legacy.test/api/sessions/default' => Http::response(['message' => 'Not Found'], 404),
        'waha-legacy.test/api/default/status' => Http::sequence()
            ->push(['message' => 'Not Found'], 404)
            ->push(['status' => 'SCAN_QR_CODE']),
        'waha-legacy.test/api/default/auth/qr*' => Http::response(
            file_get_contents(__DIR__.'/../Fixtures/test.png') ?: 'PNGDATA',
            200,
            ['Content-Type' => 'image/png'],
        ),
    ]);

    $this->actingAs($actor)
        ->get(route('gateways.waha.qr', $server))
        ->assertOk()
        ->assertJsonPath('status', 'SCAN_QR_CODE')
        ->assertJsonPath('qr', fn ($qr) => str_starts_with((string) $qr, 'data:image/png;base64,'));
});

test('qr endpoint restarts a failed waha session', function () {
    $actor = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));

    $server = WahaServer::create([
        'name' => 'WAHA Rusak',
        'base_url' => 'http://waha-failed.test',
        'api_key' => 'secret',
        'session' => 'default',
        'is_connected' => false,
    ]);

    Http::fake([
        'waha-failed.test/api/sessions/default/stop' => Http::response(['name' => 'default', 'status' => 'STOPPED']),
        'waha-failed.test/api/sessions/start' => Http::response(['name' => 'default', 'status' => 'STARTING']),
        'waha-failed.test/api/sessions/default' => Http::sequence()
            ->push(['name' => 'default', 'status' => 'FAILED'])
            ->push(['name' => 'default', 'status' => 'SCAN_QR_CODE']),
        'waha-failed.test/api/default/auth/qr*' => Http::response(
            file_get_contents(__DIR__.'/../Fixtures/test.png') ?: 'PNGDATA',
            200,
            ['Content-Type' => 'image/png'],
        ),
    ]);

    $this->actingAs($actor)
        ->get(route('gateways.waha.qr', $server))
        ->assertOk()
        ->assertJsonPath('status', 'SCAN_QR_CODE')
        ->assertJsonPath('qr', fn ($qr) => str_starts_with((string) $qr, 'data:image/png;base64,'));
});
