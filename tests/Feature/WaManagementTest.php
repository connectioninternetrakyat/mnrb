<?php

use App\Enums\PelangganStatus;
use App\Enums\UserRole;
use App\Models\AreaCode;
use App\Models\Broadcast;
use App\Models\MessageTemplate;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\Pelanggan;
use App\Models\PppProfile;
use App\Models\User;
use App\Models\WahaServer;
use Database\Seeders\SuperSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(SuperSeeder::class);

    WahaServer::create([
        'name' => 'WAHA Uji',
        'base_url' => 'http://waha.test',
        'api_key' => 'key',
        'session' => 'default',
        'is_connected' => true,
    ]);

    Http::fake([
        'waha.test/*' => Http::response(['id' => 'false_msg'], 201),
    ]);
});

function waActor(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));
}

function waPelanggan(string $name, ?int $areaId = null): Pelanggan
{
    MikrotikServer::firstOrCreate(['name' => 'RTR-Uji'], [
        'host' => '127.0.0.1',
        'port' => 1,
        'username' => 'admin',
        'password' => 'x',
        'is_default' => true,
        'timeout' => 1,
    ]);

    return Pelanggan::create([
        'name' => $name,
        'whatsapp' => '+6281234567890',
        'address' => 'Jl. Uji',
        'area_code_id' => $areaId ?? AreaCode::firstOrCreate(['code' => '05'], ['name' => 'Tegal Kota'])->id,
        'package_id' => Package::firstOrCreate(['name' => 'Paket Home 10M'], [
            'ppp_profile_id' => PppProfile::firstOrCreate([
                'mikrotik_server_id' => 1,
                'name' => 'PPPoE-10M',
            ], ['rate_limit' => '10M/10M'])->id,
            'price' => 124000,
        ])->id,
        'status' => PelangganStatus::Active->value,
    ]);
}

test('guests are redirected and outsiders forbidden from wa menus', function () {
    $this->get(route('wa.template.index'))->assertRedirect(route('login'));
    $this->get(route('wa.broadcast.index'))->assertRedirect(route('login'));

    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get(route('wa.template.index'))->assertForbidden();
    $this->actingAs($outsider)->get(route('wa.broadcast.index'))->assertForbidden();
});

test('marketing can view and send broadcasts but not manage templates', function () {
    $marketing = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::Marketing->value));

    expect($marketing->can('wa.view'))->toBeTrue();
    expect($marketing->can('wa.send'))->toBeTrue();
    expect($marketing->can('wa.manage'))->toBeFalse();
});

test('templates page lists the seeded defaults', function () {
    $this->actingAs(waActor())
        ->get(route('wa.template.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('wa/templates')
                ->has('templates', 5)
        );
});

test('template store creates or updates by slug', function () {
    $actor = waActor();

    $this->actingAs($actor)
        ->post(route('wa.template.store'), [
            'slug' => 'psb',
            'name' => 'PSB Custom',
            'body' => 'Halo {{nama}}, jadwal pasang {{jadwal}}.',
        ])
        ->assertRedirect();

    $template = MessageTemplate::query()->where('slug', 'psb')->sole();

    expect($template->name)->toBe('PSB Custom');
    expect($template->body)->toBe('Halo {{nama}}, jadwal pasang {{jadwal}}.');

    // Validation: body required.
    $this->actingAs($actor)
        ->post(route('wa.template.store'), [
            'slug' => 'psb',
            'name' => 'Tanpa Body',
        ])
        ->assertSessionHasErrors(['body']);
});

test('default built-in templates cannot be deleted', function () {
    $template = MessageTemplate::query()->where('slug', 'psb')->sole();

    $this->actingAs(waActor())
        ->delete(route('wa.template.destroy', $template))
        ->assertRedirect();

    expect(MessageTemplate::count())->toBe(5);
});

test('broadcast renders template variables per pelanggan and sends via waha', function () {
    $area = AreaCode::firstOrCreate(['code' => '05'], ['name' => 'Tegal Kota']);

    waPelanggan('Nadia Putri', $area->id);
    waPelanggan('Sinta Rahma', $area->id);
    // Different area: must NOT receive this broadcast.
    waPelanggan('Bella Anisa', AreaCode::firstOrCreate(['code' => '07'], ['name' => 'Slawi'])->id);

    $actor = waActor();

    $this->actingAs($actor)
        ->post(route('wa.broadcast.store'), [
            'message_template_id' => MessageTemplate::query()->where('slug', 'gangguan')->value('id'),
            'area_code_id' => $area->id,
        ])
        ->assertRedirect();

    $broadcast = Broadcast::query()->sole();

    expect($broadcast->recipients)->toBe(2);
    expect($broadcast->sent_count)->toBe(2);
    expect($broadcast->failed_count)->toBe(0);

    // Two WAHA calls for the two scoped recipients.
    Http::assertSentCount(2);

    // The gangguan template variables were rendered with real data.
    Http::assertSent(fn ($request) => str_contains($request['text'], 'Nadia Putri'));
});

test('broadcast to all areas reaches every active pelanggan', function () {
    waPelanggan('Pelanggan A');
    waPelanggan('Pelanggan B');

    $actor = waActor();

    $this->actingAs($actor)
        ->post(route('wa.broadcast.store'), [
            'message' => 'Maintenance jaringan malam ini pukul 23:00.',
        ])
        ->assertRedirect();

    $broadcast = Broadcast::query()->sole();

    expect($broadcast->recipients)->toBe(2);
    expect($broadcast->sent_count)->toBe(2);
    expect($broadcast->message)->toBe('Maintenance jaringan malam ini pukul 23:00.');
});
