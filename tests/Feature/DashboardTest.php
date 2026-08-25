<?php

use App\Enums\ProspectStatus;
use App\Models\AreaCode;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\PppProfile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(SuperSeeder::class);
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('marketing dashboard only shows their own prospects', function () {
    $area = AreaCode::firstOrCreate(['code' => '05'], ['name' => 'Tegal Kota']);

    MikrotikServer::firstOrCreate(['name' => 'RTR-Uji'], [
        'host' => '127.0.0.1', 'port' => 1, 'username' => 'a', 'password' => 'x', 'timeout' => 1,
    ]);
    $profile = PppProfile::firstOrCreate(['mikrotik_server_id' => 1, 'name' => 'PPPoE-10M'], ['rate_limit' => '10M/10M']);
    $package = Package::firstOrCreate(['name' => 'Paket Uji'], ['ppp_profile_id' => $profile->id, 'price' => 100000]);

    $marketingA = tap(User::factory()->create(), fn (User $u) => $u->assignRole('Marketing'));
    $marketingB = tap(User::factory()->create(), fn (User $u) => $u->assignRole('Marketing'));

    $marketingA->prospects()->create([
        'name' => 'Milik A',
        'email' => 'a@example.com',
        'whatsapp' => '+628111111111',
        'address' => 'Jl. A',
        'area_code_id' => $area->id,
        'package_id' => $package->id,
        'status' => ProspectStatus::Pending->value,
    ]);

    $marketingB->prospects()->create([
        'name' => 'Milik B',
        'email' => 'b@example.com',
        'whatsapp' => '+628122222222',
        'address' => 'Jl. B',
        'area_code_id' => $area->id,
        'package_id' => $package->id,
        'status' => ProspectStatus::Pending->value,
    ]);

    $this->actingAs($marketingA)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('stats.prospek_menunggu', 1)
                ->where('latestProspects.0.name', 'Milik A')
        );
});
