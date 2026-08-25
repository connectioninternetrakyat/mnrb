<?php

use App\Enums\UserRole;
use App\Models\MikrotikServer;
use App\Models\PppProfile;
use App\Models\PppSecret;
use App\Models\User;
use App\Services\Ppp\MikrotikPppService;
use App\Services\Ppp\PppProvisioningService;
use App\Services\Ppp\RadiusPppService;
use Database\Seeders\SuperSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(SuperSeeder::class);
});

function pppActor(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));
}

function unreachableMikrotik(): MikrotikServer
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
function sqliteRadiusService(): RadiusPppService
{
    $pdo = DB::connection()->getPdo();

    $service = new RadiusPppService($pdo);
    $service->ensureSchema();

    return $service;
}

test('guests are redirected to login', function () {
    $this->get(route('ppp.profile.index'))->assertRedirect(route('login'));
    $this->get(route('ppp.secret.index'))->assertRedirect(route('login'));
    $this->get(route('ppp.active.index'))->assertRedirect(route('login'));
});

test('users without permission cannot open pppoe pages', function () {
    $actor = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::Teknisi->value));

    $this->actingAs($actor)->get(route('ppp.profile.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('ppp.secret.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('ppp.active.index'))->assertForbidden();
});

test('superadmin can view the pppoe pages', function () {
    $actor = pppActor();

    $this->actingAs($actor)
        ->get(route('ppp.profile.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('ppp/profile')
                ->has('profiles.data', 0)
        );

    $this->actingAs($actor)
        ->get(route('ppp.secret.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('ppp/secret')->has('secrets.data', 0));

    $this->actingAs($actor)
        ->get(route('ppp.active.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('ppp/active')
                ->has('sessions.data', 0)
        );
});

test('radius service performs real group and user operations', function () {
    $radius = sqliteRadiusService();

    $radius->upsertGroup('PPPoE-10M', [
        'Framed-Pool' => 'pool-pelanggan',
        'Mikrotik-Rate-Limit' => '10M/10M',
    ]);

    expect($radius->countUsersInGroup('PPPoE-10M'))->toBe(0);

    $radius->upsertUser('budi001', 'rahasia', 'PPPoE-10M');
    $radius->upsertUser('citra01', 'rahasia2', 'PPPoE-10M');

    expect($radius->countUsersInGroup('PPPoE-10M'))->toBe(2);

    $groups = $radius->groups();
    expect($groups['PPPoE-10M']['Framed-Pool'])->toBe('pool-pelanggan');

    $users = collect($radius->users())->keyBy('username');
    expect($users['budi001']['profile'])->toBe('PPPoE-10M');
    expect($users['citra01']['profile'])->toBe('PPPoE-10M');

    // Update password + move group
    $radius->upsertUser('budi001', 'password-baru', null);

    $checks = DB::table('radcheck')->where('username', 'budi001')->get();
    expect($checks)->toHaveCount(1);
    expect($checks->first()->value)->toBe('password-baru');
    // Null profile keeps the existing membership untouched.
    expect(DB::table('radusergroup')->where('username', 'budi001')->count())->toBe(1);

    // Explicit move updates the membership.
    $radius->upsertUser('citra01', null, 'PPPoE-20M');
    expect(collect($radius->users())->firstWhere('username', 'citra01')['profile'])->toBe('PPPoE-20M');

    $radius->deleteUser('budi001');
    $radius->deleteGroup('PPPoE-10M');

    expect($radius->groups())->toBeEmpty();
    expect(DB::table('radcheck')->where('username', 'budi001')->count())->toBe(0);
});

test('provisioning writes radius first then reports mikrotik failure for secrets', function () {
    unreachableMikrotik();

    // Radius bound to sqlite so its side really persists.
    $provisioning = new PppProvisioningService(
        new MikrotikPppService(MikrotikServer::default()),
        sqliteRadiusService(),
    );

    expect(fn () => $provisioning->createSecret('budi001', 'rahasia', 'PPPoE-10M'))
        ->toThrow(RuntimeException::class);

    // Radius-first semantics: the enabled credential survived.
    $rows = DB::table('radcheck')->where('username', 'budi001')->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->value)->toBe('rahasia');

    $memberships = DB::table('radusergroup')->where('username', 'budi001')->pluck('groupname');

    expect($memberships)->toContain('PPPoE-10M');
});

test('provisioning profile creation fails gracefully without radius', function () {
    MikrotikServer::query()->update(['is_default' => false]);

    $server = unreachableMikrotik();

    $provisioning = new PppProvisioningService(
        new MikrotikPppService($server),
        null,
    );

    expect(fn () => $provisioning->createProfile([
        'name' => 'PPPoE-10M',
    ]))->toThrow(RuntimeException::class, 'radius');
});

test('profile store validation requires a name', function () {
    $this->actingAs(pppActor());

    $this->post(route('ppp.profile.store'), [])
        ->assertSessionHasErrors(['name']);
});

test('secret store validation enforces username format and required password', function () {
    $this->actingAs(pppActor());

    $this->post(route('ppp.secret.store'), [
        'name' => 'invalid username!',
        'profile' => 'PPPoE-10M',
    ])->assertSessionHasErrors(['name', 'password']);
});

test('profile index paginates ten per page and searches by name', function () {
    $actor = pppActor();
    $server = unreachableMikrotik();

    foreach (range(1, 12) as $i) {
        PppProfile::create([
            'mikrotik_server_id' => $server->id,
            'name' => sprintf('PPPoE-%02dM', $i),
            'rate_limit' => sprintf('%dM/%dM', $i, $i),
        ]);
    }

    $this->actingAs($actor)
        ->get(route('ppp.profile.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('ppp/profile')
                ->has('profiles.data', 10)
                ->where('profiles.total', 12)
                ->where('profiles.current_page', 1)
        );

    $this->actingAs($actor)
        ->get(route('ppp.profile.index', ['search' => 'PPPoE-11']))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('ppp/profile')
                ->has('profiles.data', 1)
                ->where('profiles.data.0.name', 'PPPoE-11M')
        );
});

test('secret index searches by username', function () {
    $actor = pppActor();
    $server = MikrotikServer::default() ?? unreachableMikrotik();

    foreach (['cms_lab', 'bjp_cek', 'budi001'] as $name) {
        PppSecret::create([
            'mikrotik_server_id' => $server->id,
            'name' => $name,
            'password' => 'secret',
            'profile' => '10M',
        ]);
    }

    $this->actingAs($actor)
        ->get(route('ppp.secret.index', ['search' => 'budi']))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('ppp/secret')
                ->has('secrets.data', 1)
                ->where('secrets.data.0.name', 'budi001')
        );
});
