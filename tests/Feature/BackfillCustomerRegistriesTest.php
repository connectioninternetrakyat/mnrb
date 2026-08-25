<?php

use App\Enums\CustomerStatus;
use App\Enums\PelangganStatus;
use App\Enums\ProspectStatus;
use App\Enums\PsbTicketStatus;
use App\Enums\UserRole;
use App\Models\AreaCode;
use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\Pelanggan;
use App\Models\PppProfile;
use App\Models\PppSecret;
use App\Models\Prospect;
use App\Models\PsbTicket;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Seeders\SuperSeeder;

beforeEach(function () {
    $this->seed(SuperSeeder::class);
});

function backfillActor(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));
}

/**
 * Legacy data: a prospect + PSB ticket WITHOUT the customer registries,
 * exactly how rows looked before the real integration existed.
 */
function legacyTicket(string $status, ?CarbonInterface $finishedAt = null): PsbTicket
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

    PppSecret::firstOrCreate(['name' => '05_bellasari'], [
        'mikrotik_server_id' => 1,
        'password' => 'secret',
        'profile' => 'PPPoE-10M',
    ]);

    $actor = backfillActor();
    $prospect = $actor->prospects()->create([
        'name' => 'Bella Sari',
        'email' => 'bella@example.com',
        'whatsapp' => '0812987654321',
        'address' => 'Jl. Kenanga No. 3',
        'maps_url' => 'https://maps.app.goo.gl/bella-sari',
        'area_code_id' => $area->id,
        'package_id' => $package->id,
        'status' => ProspectStatus::Approved->value,
        'approved_at' => now()->subDay(),
    ]);

    return PsbTicket::create([
        'ticket_number' => PsbTicket::generateNumber(),
        'prospect_id' => $prospect->id,
        'scheduled_at' => now()->subDay(),
        'finished_at' => $finishedAt,
        'status' => $status,
    ]);
}

test('backfill creates pelanggan and billing customer for legacy tickets', function () {
    $ticket = legacyTicket(
        PsbTicketStatus::Completed->value,
        now()->subHours(2),
    );

    expect(Pelanggan::count())->toBe(0);
    expect(Customer::count())->toBe(0);

    $this->artisan('psb:sync-customers')->assertSuccessful();

    expect(Pelanggan::count())->toBe(1);
    expect(Customer::count())->toBe(1);

    $pelanggan = Pelanggan::query()->sole();

    expect($pelanggan->psb_ticket_id)->toBe($ticket->id);
    expect($pelanggan->name)->toBe('Bella Sari');
    expect($pelanggan->maps_url)->toBe('https://maps.app.goo.gl/bella-sari');
    expect($pelanggan->status)->toBe(PelangganStatus::Active);
    expect($pelanggan->activated_at?->format('YmdHis'))
        ->toBe($ticket->finished_at?->format('YmdHis'));
    // The deterministic PPP secret of the prospect gets re-linked.
    expect($pelanggan->ppp_secret_id)->not->toBeNull();
    expect($pelanggan->pppSecret?->name)->toBe('05_bellasari');

    $customer = Customer::query()->sole();

    expect($customer->psb_ticket_id)->toBe($ticket->id);
    expect($customer->status)->toBe(CustomerStatus::Active);

    // Idempotent: running twice must not duplicate anything.
    $this->artisan('psb:sync-customers')->assertSuccessful();

    expect(Pelanggan::count())->toBe(1);
    expect(Customer::count())->toBe(1);
});

test('backfill stops the registries for cancelled legacy tickets', function () {
    legacyTicket(PsbTicketStatus::Cancelled->value);

    $this->artisan('psb:sync-customers')->assertSuccessful();

    expect(Pelanggan::query()->sole()->status)->toBe(PelangganStatus::Stopped);
    expect(Customer::query()->sole()->status)->toBe(CustomerStatus::Suspended);
});
