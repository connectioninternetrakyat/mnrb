<?php

use App\Enums\UserRole;
use App\Models\GenieacsServer;
use App\Models\Onu;
use App\Models\User;
use Database\Seeders\SuperSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(SuperSeeder::class);

    GenieacsServer::create([
        'name' => 'ACS Uji',
        'base_url' => 'http://genie.test:7557',
        'username' => 'admin',
        'password' => 'secret',
        'is_default' => true,
    ]);

    // A realistic flattened parameter tree of a PPPoE ONT.
    $this->deviceDoc = [
        '_id' => 'device-1',
        '_lastInform' => '2026-08-23T03:00:00.000Z',
        'DeviceID' => [
            '_SerialNumber' => 'SN-001',
            '_ProductClass' => 'EC230-G1',
            '_OUI' => 'AABBCC',
        ],
        'InternetGatewayDevice.DeviceInfo.ModelName' => ['_value' => 'EC230-G1 v1'],
        'InternetGatewayDevice.DeviceInfo.SoftwareVersion' => ['_value' => '2.10'],
        'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.Name' => ['_value' => 'pppoe-bridge'],
        'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ConnectionType' => ['_value' => 'IP_Routed'],
        'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.Status' => ['_value' => 'Connected'],
        'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress' => ['_value' => '103.11.1.5'],
        'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID' => ['_value' => 'WiFi-Budi'],
        'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase' => ['_value' => 'passwordlama'],
        'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel' => ['_value' => '6'],
        'InternetGatewayDevice.LANDevice.1.Hosts.Host.1.HostName' => ['_value' => 'android-budi'],
        'InternetGatewayDevice.LANDevice.1.Hosts.Host.1.IPAddress' => ['_value' => '192.168.1.50'],
        'InternetGatewayDevice.LANDevice.1.Hosts.Host.1.Active' => ['_value' => '1'],
        'InternetGatewayDevice.DeviceInfo.X_HW_Temperature' => ['_value' => '48'],
    ];

    Http::fake([
        'http://genie.test:7557/devices/device-1/tasks*' => Http::response(['_id' => 'task-1'], 201),
        'http://genie.test:7557/devices/device-1' => Http::response($this->deviceDoc, 200),
        'http://genie.test:7557/devices*' => Http::response([$this->deviceDoc], 200),
    ]);
});

function onuActor(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));
}

function registeredOnu(): Onu
{
    return Onu::firstOrCreate(
        ['genieacs_id' => 'device-1'],
        [
            'name' => 'Budi Santoso',
            'pppoe_user' => 'budi001',
            'description' => 'Pelanggan talasalak',
        ],
    );
}

test('guests are redirected to login', function () {
    $this->get(route('ftth.onu.index'))->assertRedirect(route('login'));
});

test('users without permission cannot open onu pages', function () {
    $actor = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::Teknisi->value));

    $this->actingAs($actor)->get(route('ftth.onu.index'))->assertForbidden();
});

test('superadmin sees devices merged with customer mapping', function () {
    registeredOnu();

    $this->actingAs(onuActor())
        ->get(route('ftth.onu.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('ftth/onu')
                ->has('devices.data', 1)
                ->where('devices.data.0.customer_name', 'Budi Santoso')
                ->where('devices.data.0.pppoe_user', 'budi001')
                ->where('devices.data.0.serial', 'SN-001')
        );
});

test('index search matches customer name or pppoe user', function () {
    registeredOnu();
    $actor = onuActor();

    $this->actingAs($actor)
        ->get(route('ftth.onu.index', ['search' => 'budi']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('devices.data', 1));

    $this->actingAs($actor)
        ->get(route('ftth.onu.index', ['search' => 'tidakada']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('devices.data', 0));
});

test('register requires a unique genieacs id and a name', function () {
    registeredOnu();
    $actor = onuActor();

    $this->actingAs($actor)
        ->post(route('ftth.onu.store'), [
            'genieacs_id' => 'device-1',
            'name' => 'Duplikat',
        ])
        ->assertSessionHasErrors(['genieacs_id']);

    $this->actingAs($actor)
        ->post(route('ftth.onu.store'), [
            'genieacs_id' => 'device-2',
            'name' => '',
        ])
        ->assertSessionHasErrors(['name']);
});

test('detail parses wan wlan hosts temperature from genieacs', function () {
    registeredOnu();

    $this->actingAs(onuActor())
        ->get(route('ftth.onu.show', registeredOnu()))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('ftth/onu-detail')
                ->where('identity.serial', 'SN-001')
                ->where('wans.0.name', 'pppoe-bridge')
                ->where('wans.0.external_ip', '103.11.1.5')
                ->where('wlans.0.ssid', 'WiFi-Budi')
                ->where('wlans.0.password', 'passwordlama')
                ->where('hosts.0.hostname', 'android-budi')
                ->where('temperatures.0.value', '48')
        );
});

test('reboot queues a real task payload', function () {
    registeredOnu();

    $this->actingAs(onuActor())
        ->post(route('ftth.onu.reboot', registeredOnu()))
        ->assertRedirect();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/devices/device-1/tasks')
            && $request['name'] === 'reboot';
    });
});

test('editing wlan pushes ssid password and security parameters', function () {
    registeredOnu();
    $onu = registeredOnu();

    $this->actingAs(onuActor())
        ->put(route('ftth.onu.wlan.edit', $onu), [
            'path' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1',
            'ssid' => 'WiFi-Baru',
            'password' => 'passwordbaru99',
        ])
        ->assertRedirect();

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/tasks')) {
            return false;
        }

        $values = collect($request['parameterValues'] ?? []);

        return $request['name'] === 'setParameterValues'
            && $values->contains(fn ($v) => str_contains($v[0], '.SSID') && $v[1] === 'WiFi-Baru')
            && $values->contains(fn ($v) => str_contains($v[0], '.KeyPassphrase') && $v[1] === 'passwordbaru99')
            && $values->contains(fn ($v) => str_ends_with($v[0], '.BeaconType') && $v[1] === 'WPAand11i');
    });
});

test('adding wan queues an addObject task at the connection device path', function () {
    registeredOnu();

    $this->actingAs(onuActor())
        ->post(route('ftth.onu.wan.add', registeredOnu()), [
            'path' => 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1',
            'name' => 'wan-baru',
            'connection_type' => 'IP_Bridged',
        ])
        ->assertRedirect();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/tasks')
            && $request['name'] === 'addObject'
            && $request['objectName'] === 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.';
    });
});
