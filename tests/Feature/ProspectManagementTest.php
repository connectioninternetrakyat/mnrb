<?php

use App\Enums\ProspectStatus;
use App\Enums\UserRole;
use App\Models\AreaCode;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\PppProfile;
use App\Models\Prospect;
use App\Models\User;
use App\Services\Ppp\MikrotikPppService;
use App\Services\Ppp\PppProvisioningService;
use App\Services\Ppp\RadiusPppService;
use App\Services\Prospect\ProspectApprovalService;
use App\Services\Prospect\ProspectCredentialService;
use Database\Seeders\SuperSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(SuperSeeder::class);
});

function prospectActor(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));
}

function prospectMikrotik(): MikrotikServer
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

/**
 * Radius service bound to the test sqlite connection so the SQL logic
 * runs for real without a live MySQL/PostgreSQL server.
 */
function prospectRadiusService(): RadiusPppService
{
    $service = new RadiusPppService(DB::connection()->getPdo());
    $service->ensureSchema();

    return $service;
}

function prospectFixture(): array
{
    $server = prospectMikrotik();

    $area = AreaCode::create(['code' => '05', 'name' => 'Tegal Kota']);
    $profile = PppProfile::create([
        'mikrotik_server_id' => $server->id,
        'name' => 'PPPoE-10M',
        'rate_limit' => '10M/10M',
    ]);
    $package = Package::create([
        'name' => 'Paket Home 10M',
        'ppp_profile_id' => $profile->id,
        'price' => 150000,
    ]);

    return [$area, $profile, $package];
}

function validProspectPayload(array $area, array $package): array
{
    return [
        'name' => 'Nadia Putri',
        'email' => 'nadia@example.com',
        'whatsapp' => '+6281234567890',
        'address' => 'Jl. Merdeka No. 10, Tegal',
        'area_code_id' => $area['id'],
        'package_id' => $package['id'],
        'maps_url' => 'https://maps.app.goo.gl/abc',
        'latitude' => -7.154157,
        'longitude' => 108.333915,
        'note' => 'Minat bulan depan',
    ];
}

test('credential logic maps first letter A=1..Z=26 and normalizes name', function () {
    expect(ProspectCredentialService::build('05', 'Nadia Putri'))->toBe([
        'username' => '05_nadiaputri',
        'password' => '14nadiaputri',
    ]);

    expect(ProspectCredentialService::build('01', 'Budi Santoso'))->toBe([
        'username' => '01_budisantoso',
        'password' => '2budisantoso',
    ]);

    expect(ProspectCredentialService::build('12', 'Andi'))->toBe([
        'username' => '12_andi',
        'password' => '1andi',
    ]);
});

test('credential logic rejects names not starting with a letter', function () {
    ProspectCredentialService::build('05', '123Nama');
})->throws(RuntimeException::class);

test('guests are redirected to login', function () {
    $this->get(route('prospects.index'))->assertRedirect(route('login'));
});

test('marketing can input prospects but cannot approve them', function () {
    [$area, , $package] = prospectFixture();

    $marketing = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::Marketing->value));

    $this->actingAs($marketing)
        ->get(route('prospects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('prospects/index'));

    $this->actingAs($marketing)
        ->post(route('prospects.store'), validProspectPayload($area->toArray(), ['id' => $package->id]))
        ->assertRedirect();

    $prospect = DB::table('prospects')->sole();

    // Pembawa is captured automatically from the logged-in user.
    expect($prospect->user_id)->toBe($marketing->id);
    expect($prospect->status)->toBe(ProspectStatus::Pending->value);

    $this->actingAs($marketing)
        ->post(route('prospects.approve', ['prospect' => $prospect->id]))
        ->assertForbidden();
});

test('marketing can resolve google maps coordinates for the prospect form', function () {
    $marketing = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::Marketing->value));

    // Invalid payload proves authorization passed: validation answers 422,
    // while a missing maps.resolve permission would answer 403.
    $this->actingAs($marketing)
        ->post(route('ftth.maps.resolve'), [])
        ->assertSessionHasErrors(['url']);
});

test('users without maps permission cannot resolve coordinates', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->post(route('ftth.maps.resolve'), [])->assertForbidden();
});

test('users without permission cannot open prospects page', function () {
    $teknisi = User::factory()->create();

    $this->actingAs($teknisi)->get(route('prospects.index'))->assertForbidden();
});

test('store validation requires the core fields and letter-first name', function () {
    $this->actingAs(prospectActor());

    $this->post(route('prospects.store'), [])
        ->assertSessionHasErrors(['name', 'email', 'whatsapp', 'address', 'area_code_id', 'package_id']);

    $this->post(route('prospects.store'), [
        'name' => '123DiawaliAngka',
        'email' => 'bukan-email',
        'whatsapp' => '08123',
        'address' => '-',
        'area_code_id' => 99999,
        'package_id' => 99999,
    ])->assertSessionHasErrors(['name', 'email', 'whatsapp', 'area_code_id', 'package_id']);
});

test('store accepts whatsapp in 08 and +62 formats', function () {
    $actor = prospectActor();
    [$area, , $package] = prospectFixture();
    $payload = validProspectPayload($area->toArray(), ['id' => $package->id]);

    $this->actingAs($actor)
        ->post(route('prospects.store'), [...$payload, 'whatsapp' => '081234567890'])
        ->assertSessionHasNoErrors();

    $this->actingAs($actor)
        ->post(route('prospects.store'), [...$payload, 'whatsapp' => '+6281234567890'])
        ->assertSessionHasNoErrors();

    $this->actingAs($actor)
        ->post(route('prospects.store'), [...$payload, 'whatsapp' => '6281234567890'])
        ->assertSessionHasErrors(['whatsapp']);

    $this->actingAs($actor)
        ->post(route('prospects.store'), [...$payload, 'whatsapp' => '08123'])
        ->assertSessionHasErrors(['whatsapp']);
});

test('index paginates ten per page with search and status filter', function () {
    [$area, , $package] = prospectFixture();
    $actor = prospectActor();
    $payload = validProspectPayload($area->toArray(), ['id' => $package->id]);

    foreach (range(1, 12) as $i) {
        $actor->prospects()->create([
            ...$payload,
            'name' => sprintf('Calon-%02d', $i),
            'status' => ProspectStatus::Pending->value,
        ]);
    }

    $this->actingAs($actor)
        ->get(route('prospects.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('prospects/index')
                ->has('prospects.data', 10)
                ->where('prospects.total', 12)
                ->where('prospects.current_page', 1)
        );

    $this->actingAs($actor)
        ->get(route('prospects.index', ['search' => 'Calon-11']))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('prospects.data', 1)
                ->where('prospects.data.0.name', 'Calon-11')
                ->where('prospects.data.0.area_code', '05')
                ->where('prospects.data.0.package_name', 'Paket Home 10M')
                ->where('prospects.data.0.brought_by', $actor->name)
                ->where('prospects.data.0.latitude', -7.154157)
        );

    $this->actingAs($actor)
        ->get(route('prospects.index', ['status' => 'ditolak']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('prospects.data', 0));
});

test('only pending prospects can be edited, approved ones cannot be deleted', function () {
    [$area, , $package] = prospectFixture();
    $actor = prospectActor();

    $pending = $actor->prospects()->create([
        ...validProspectPayload($area->toArray(), ['id' => $package->id]),
        'status' => ProspectStatus::Pending->value,
    ]);

    $approved = $actor->prospects()->create([
        ...validProspectPayload($area->toArray(), ['id' => $package->id]),
        'name' => 'Sudah Setuju',
        'status' => ProspectStatus::Approved->value,
    ]);

    $this->actingAs($actor)
        ->put(route('prospects.update', $pending), [
            ...validProspectPayload($area->toArray(), ['id' => $package->id]),
            'name' => 'Nama Baru',
        ])
        ->assertRedirect();

    expect($pending->fresh()->name)->toBe('Nama Baru');

    // Approved prospects are frozen.
    $this->actingAs($actor)
        ->put(route('prospects.update', $approved), [
            ...validProspectPayload($area->toArray(), ['id' => $package->id]),
            'name' => 'Diganti',
        ])
        ->assertRedirect();

    expect($approved->fresh()->name)->toBe('Sudah Setuju');

    // And cannot be deleted.
    $this->actingAs($actor)->delete(route('prospects.destroy', $approved))->assertRedirect();
    expect(Prospect::whereKey($approved->id)->exists())->toBeTrue();

    $this->actingAs($actor)->delete(route('prospects.destroy', $pending));
    expect(Prospect::whereKey($pending->id)->exists())->toBeFalse();
});

test('approve creates the ppp secret for real on radius and mikrotik mirror', function () {
    [$area, $profile, $package] = prospectFixture();
    $actor = prospectActor();

    $provisioning = new PppProvisioningService(
        tap(Mockery::mock(MikrotikPppService::class, [prospectMikrotik()]), function ($mock): void {
            $mock->shouldReceive('createSecret')
                ->once()
                ->with('05_nadiaputri', '14nadiaputri', 'PPPoE-10M', true, 'prospect #1 - Nadia Putri')
                ->andReturn('.id*1');
        }),
        prospectRadiusService(),
    );

    $prospect = $actor->prospects()->create([
        ...validProspectPayload($area->toArray(), ['id' => $package->id]),
        'status' => ProspectStatus::Pending->value,
    ]);

    $credentials = (new ProspectApprovalService)->approve($prospect, null, $provisioning);

    expect($credentials['username'])->toBe('05_nadiaputri');
    expect($credentials['password'])->toBe('14nadiaputri');

    $fresh = $prospect->fresh();
    expect($fresh->status)->toBe(ProspectStatus::Approved);
    expect($fresh->approved_at)->not->toBeNull();

    // Radius rows really exist.
    $check = DB::table('radcheck')->where('username', '05_nadiaputri')->sole();
    expect($check->value)->toBe('14nadiaputri');
    expect(collect(DB::table('radusergroup')->where('username', '05_nadiaputri')->pluck('groupname')))->toContain('PPPoE-10M');

    // Local secret mirror is in sync.
    $secret = DB::table('ppp_secrets')->where('name', '05_nadiaputri')->sole();
    expect($secret->profile)->toBe('PPPoE-10M');
    expect((bool) $secret->disabled_on_mikrotik)->toBeTrue();
});

test('double approval is rejected', function () {
    [$area, , $package] = prospectFixture();
    $actor = prospectActor();

    $provisioning = new PppProvisioningService(
        tap(Mockery::mock(MikrotikPppService::class, [prospectMikrotik()]), function ($mock): void {
            $mock->shouldReceive('createSecret')->once()->andReturn('.id*1');
        }),
        prospectRadiusService(),
    );
    $service = new ProspectApprovalService;

    $prospect = $actor->prospects()->create([
        ...validProspectPayload($area->toArray(), ['id' => $package->id]),
        'status' => ProspectStatus::Pending->value,
    ]);

    $service->approve($prospect, null, $provisioning);

    $service->approve($prospect, null, $provisioning);
})->throws(RuntimeException::class);

test('duplicate username from another prospect is blocked before provisioning', function () {
    [$area, , $package] = prospectFixture();
    $actor = prospectActor();

    $provisioning = new PppProvisioningService(
        tap(Mockery::mock(MikrotikPppService::class, [prospectMikrotik()]), function ($mock): void {
            $mock->shouldReceive('createSecret')->once()->andReturn('.id*1');
        }),
        prospectRadiusService(),
    );
    $service = new ProspectApprovalService;

    $first = $actor->prospects()->create([
        ...validProspectPayload($area->toArray(), ['id' => $package->id]),
        'status' => ProspectStatus::Pending->value,
    ]);

    // Same person name -> identical username -> must be blocked by the guard.
    $second = $actor->prospects()->create([
        ...validProspectPayload($area->toArray(), ['id' => $package->id]),
        'email' => 'nadia-duplikat@example.com',
        'status' => ProspectStatus::Pending->value,
    ]);

    $service->approve($first, null, $provisioning);

    // Mirror row already holds the username -> second approval fails safely.
    $service->approve($second, null, $provisioning);
})->throws(RuntimeException::class);

test('radius-first semantics keep the credential alive when mikrotik fails', function () {
    [$area, , $package] = prospectFixture();
    $actor = prospectActor();

    // Radius bound to sqlite so its side really persists; router unreachable.
    $provisioning = new PppProvisioningService(
        new MikrotikPppService(MikrotikServer::default()),
        prospectRadiusService(),
    );

    $prospect = $actor->prospects()->create([
        ...validProspectPayload($area->toArray(), ['id' => $package->id]),
        'status' => ProspectStatus::Pending->value,
    ]);

    expect(fn () => (new ProspectApprovalService)->approve($prospect, null, $provisioning))
        ->toThrow(RuntimeException::class);

    // The enabled radius credential survived even though approval failed...
    expect(DB::table('radcheck')->where('username', '05_nadiaputri')->count())->toBe(1);

    // ...so the prospect stays pending and can be re-approved later.
    expect($prospect->fresh()->status)->toBe(ProspectStatus::Pending);
});

test('reject flips only pending prospects', function () {
    [$area, , $package] = prospectFixture();
    $actor = prospectActor();

    $pending = $actor->prospects()->create([
        ...validProspectPayload($area->toArray(), ['id' => $package->id]),
        'status' => ProspectStatus::Pending->value,
    ]);

    $this->actingAs($actor)->post(route('prospects.reject', $pending))->assertRedirect();

    expect($pending->fresh()->status)->toBe(ProspectStatus::Rejected);

    // Already processed prospects cannot be rejected twice.
    $this->actingAs($actor)->post(route('prospects.reject', $pending))->assertRedirect();

    expect($pending->fresh()->status)->toBe(ProspectStatus::Rejected);
});
