<?php

use App\Enums\CustomerStatus;
use App\Enums\PelangganStatus;
use App\Enums\ProspectStatus;
use App\Enums\PsbTicketStatus;
use App\Enums\UserRole;
use App\Models\AreaCode;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\Pelanggan;
use App\Models\PppProfile;
use App\Models\PsbTicket;
use App\Models\User;
use App\Services\Billing\MonthlyBillService;
use App\Services\Ppp\MikrotikPppService;
use App\Services\Ppp\PppProvisioningService;
use App\Services\Ppp\RadiusPppService;
use App\Services\Prospect\ProspectApprovalService;
use Database\Seeders\SuperSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(SuperSeeder::class);
});

function pelangganActor(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));
}

/**
 * Approve a prospect through the real service so the pelanggan record is
 * auto-created by the platform integration itself.
 */
function approvedPelangganFixture(): array
{
    MikrotikServer::firstOrCreate(['name' => 'RTR-Uji'], [
        'host' => '127.0.0.1',
        'port' => 1,
        'username' => 'admin',
        'password' => 'x',
        'is_default' => true,
        'timeout' => 1,
    ]);

    $area = AreaCode::firstOrCreate(['code' => '05'], ['name' => 'Tegal Kota']);
    $profile = PppProfile::firstOrCreate([
        'mikrotik_server_id' => 1,
        'name' => 'PPPoE-10M',
    ], ['rate_limit' => '10M/10M']);
    $package = Package::firstOrCreate(['name' => 'Paket Home 10M'], [
        'ppp_profile_id' => $profile->id,
        'price' => 124000,
    ]);

    $actor = pelangganActor();
    $technician = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::Teknisi->value));

    $provisioning = new PppProvisioningService(
        tap(Mockery::mock(MikrotikPppService::class, [MikrotikServer::default()]), function ($mock): void {
            $mock->shouldReceive('createSecret')->once()->andReturn('.id*1');
        }),
        tap(new RadiusPppService(DB::connection()->getPdo()), fn ($service) => $service->ensureSchema()),
    );

    $prospect = $actor->prospects()->create([
        'name' => 'Nadia Putri',
        'email' => 'nadia@example.com',
        'whatsapp' => '+6281234567890',
        'address' => 'Jl. Merdeka No. 10',
        'maps_url' => 'https://maps.app.goo.gl/nadia-utama',
        'area_code_id' => $area->id,
        'package_id' => $package->id,
        'status' => ProspectStatus::Pending->value,
    ]);

    (new ProspectApprovalService)->approve($prospect, [
        'user_id' => $technician->id,
        'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
    ], $provisioning);

    $pelanggan = Pelanggan::query()->sole();

    return [$actor, $pelanggan];
}

test('approving a prospect automatically creates the pelanggan record', function () {
    [$actor, $pelanggan] = approvedPelangganFixture();

    expect($pelanggan->name)->toBe('Nadia Putri');
    expect($pelanggan->status)->toBe(PelangganStatus::Active);
    expect($pelanggan->areaCode?->code)->toBe('05');
    expect($pelanggan->package?->name)->toBe('Paket Home 10M');
    expect($pelanggan->psb_ticket_id)->not->toBeNull();
    expect($pelanggan->joined_at)->not->toBeNull();
    expect($pelanggan->maps_url)->toBe('https://maps.app.goo.gl/nadia-utama');

    // Real billing integration: the conversion also lands in the billing
    // customer registry, linked to the same PSB ticket.
    $customer = Customer::query()->sole();

    expect($customer->psb_ticket_id)->toBe($pelanggan->psb_ticket_id);
    expect($customer->ppp_secret_id)->toBe($pelanggan->ppp_secret_id);
    expect($customer->status)->toBe(CustomerStatus::Active);
});

test('guests are redirected and outsiders forbidden', function () {
    $this->get(route('pelanggan.index'))->assertRedirect(route('login'));

    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get(route('pelanggan.index'))->assertForbidden();
});

test('superadmin can list pelanggans with pagination and filters', function () {
    [$actor] = approvedPelangganFixture();

    $this->actingAs($actor)
        ->get(route('pelanggan.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('pelanggan/index')
                ->has('pelanggans.data', 1)
                ->where('pelanggans.data.0.name', 'Nadia Putri')
                ->where('pelanggans.data.0.area_code', '05')
                ->where('pelanggans.data.0.secret_username', '05_nadiaputri')
        );

    $this->actingAs($actor)
        ->get(route('pelanggan.index', ['status' => 'berhenti']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('pelanggans.data', 0));
});

test('pelanggan store and update validate input', function () {
    $this->actingAs(pelangganActor());

    $this->post(route('pelanggan.store'), [])
        ->assertSessionHasErrors(['name', 'whatsapp', 'address', 'area_code_id', 'package_id']);
});

test('stop disables the real ppp secret then marks berhenti', function () {
    [$actor, $pelanggan] = approvedPelangganFixture();

    $mikrotik = Mockery::mock(MikrotikPppService::class, [MikrotikServer::default()]);
    $mikrotik->shouldReceive('updateSecret')
        ->once()
        ->with('05_nadiaputri', ['disabled' => true]);
    $this->instance(MikrotikPppService::class, $mikrotik);

    $this->actingAs($actor)
        ->post(route('pelanggan.stop', $pelanggan))
        ->assertRedirect();

    expect($pelanggan->fresh()->status)->toBe(PelangganStatus::Stopped);

    // Reactivate re-enables with the package profile.
    $reactivateMock = Mockery::mock(MikrotikPppService::class, [MikrotikServer::default()]);
    $reactivateMock->shouldReceive('updateSecret')
        ->once()
        ->with('05_nadiaputri', Mockery::on(fn ($arg) => ($arg['disabled'] ?? null) === false));
    $this->instance(MikrotikPppService::class, $reactivateMock);

    $this->actingAs($actor)
        ->post(route('pelanggan.activate', $pelanggan))
        ->assertRedirect();

    expect($pelanggan->fresh()->status)->toBe(PelangganStatus::Active);
});

test('delete is blocked while a ppp secret is linked', function () {
    [$actor, $pelanggan] = approvedPelangganFixture();

    $this->actingAs($actor)
        ->delete(route('pelanggan.destroy', $pelanggan))
        ->assertRedirect();

    expect(Pelanggan::count())->toBe(1);

    // Unlink first, then deletion succeeds.
    $pelanggan->update(['ppp_secret_id' => null]);

    $this->actingAs($actor)
        ->delete(route('pelanggan.destroy', $pelanggan))
        ->assertRedirect();

    expect(Pelanggan::count())->toBe(0);
});

test('finishing a psb ticket stamps activation on the pelanggan record', function () {
    [$actor, $pelanggan] = approvedPelangganFixture();
    $ticket = PsbTicket::query()->sole();

    $ticket->update(['status' => PsbTicketStatus::InProgress->value]);

    $this->actingAs($actor)
        ->post(route('tickets.psb.finish.store', $ticket), [
            'payment_method' => 'tunai',
            'check_pppoe_account' => '1',
            'check_customer_active' => '1',
            'check_vlan_tr069' => '1',
        ])
        ->assertRedirect(route('tickets.psb.show', $ticket));

    expect($pelanggan->fresh()->status)->toBe(PelangganStatus::Active);
    expect($pelanggan->fresh()->activated_at)->not->toBeNull();
    expect(Customer::query()->sole()->status)->toBe(CustomerStatus::Active);

    // The pelanggan page exposes the real integration data.
    $this->actingAs($actor)
        ->get(route('pelanggan.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('pelanggans.data.0.source', 'prospek')
                ->where('pelanggans.data.0.ticket_number', $ticket->ticket_number)
                ->where('pelanggans.data.0.invoice_status', 'lunas')
                ->where('pelanggans.data.0.activated_at', fn ($value) => $value !== null)
        );
});

test('cancelling a psb ticket stops the phantom active customer', function () {
    [$actor, $pelanggan] = approvedPelangganFixture();
    $ticket = PsbTicket::query()->sole();

    $this->actingAs($actor)
        ->post(route('tickets.psb.cancel', $ticket), [
            'cancel_reason' => 'Pelanggan membatalkan pemasangan.',
        ])
        ->assertRedirect(route('tickets.psb.show', $ticket));

    expect($pelanggan->fresh()->status)->toBe(PelangganStatus::Stopped);
    expect(Customer::query()->sole()->status)->toBe(CustomerStatus::Suspended);
});

test('monthly bills charge the current pelanggan package after an upgrade', function () {
    [$actor, $pelanggan] = approvedPelangganFixture();
    $ticket = PsbTicket::query()->sole();

    $ticket->update(['status' => PsbTicketStatus::InProgress->value]);

    $this->actingAs($actor)
        ->post(route('tickets.psb.finish.store', $ticket), [
            'payment_method' => 'tunai',
            'check_pppoe_account' => '1',
            'check_customer_active' => '1',
            'check_vlan_tr069' => '1',
        ])
        ->assertRedirect();

    // Upgrade the package in customer management.
    $upgradedProfile = PppProfile::create([
        'mikrotik_server_id' => 1,
        'name' => 'PPPoE-20M',
        'rate_limit' => '20M/20M',
    ]);

    $upgraded = Package::create([
        'name' => 'Paket Home 20M',
        'ppp_profile_id' => $upgradedProfile->id,
        'price' => 200000,
    ]);

    $pelanggan->update(['package_id' => $upgraded->id]);

    expect(app(MonthlyBillService::class)->generateForCurrentPeriod())->toBe(1);

    $bill = Bill::query()->sole();

    expect((int) $bill->monthly_price)->toBe(200000);
    expect((int) $bill->total)->toBe(200000);
});

test('pelanggan detail page shows the fully integrated data', function () {
    [$actor] = approvedPelangganFixture();
    $ticket = PsbTicket::query()->sole();

    $ticket->update(['status' => PsbTicketStatus::InProgress->value]);

    $this->actingAs($actor)
        ->post(route('tickets.psb.finish.store', $ticket), [
            'payment_method' => 'tunai',
            'check_pppoe_account' => '1',
            'check_customer_active' => '1',
            'check_vlan_tr069' => '1',
        ])
        ->assertRedirect();

    $pelanggan = Pelanggan::query()->sole();

    $this->actingAs($actor)
        ->get(route('pelanggan.show', $pelanggan))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('pelanggan/detail')
                ->where('pelanggan.name', 'Nadia Putri')
                ->where('pelanggan.secret_username', '05_nadiaputri')
                ->where(
                    'pelanggan.maps_url',
                    'https://maps.app.goo.gl/nadia-utama',
                )
                ->where('ticket.ticket_number', $ticket->ticket_number)
                ->where('ticket.status', 'selesai')
                ->where('prospect.name', 'Nadia Putri')
                ->where('invoice.status', 'lunas')
                ->where('invoice.total', fn ($value) => (int) $value > 0)
                ->where('summary.bills_count', 0)
                ->where('summary.outstanding', 0)
        );
});
