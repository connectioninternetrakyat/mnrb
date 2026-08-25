<?php

use App\Enums\InvoiceStatus;
use App\Enums\ProspectStatus;
use App\Enums\PsbTicketStatus;
use App\Enums\UserRole;
use App\Models\AreaCode;
use App\Models\Bill;
use App\Models\BillingSetting;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\PppProfile;
use App\Models\PppSecret;
use App\Models\Prospect;
use App\Models\PsbTicket;
use App\Models\User;
use App\Models\WahaServer;
use App\Services\Ppp\MikrotikPppService;
use App\Services\Ppp\PppProvisioningService;
use App\Services\Ppp\RadiusPppService;
use App\Services\Prospect\ProspectApprovalService;
use App\Services\Prospect\ProspectCredentialService;
use Database\Seeders\SuperSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(SuperSeeder::class);
});

function automationActor(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));
}

function actingAsAutomationActor()
{
    return test()->actingAs(automationActor());
}

/**
 * Completed ticket + unpaid current-period bill + secret mirror.
 *
 * @return array{0: PsbTicket, 1: PppSecret, 2: Prospect}
 */
function automationFixture(): array
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

    $actor = automationActor();
    $technician = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::Teknisi->value));

    $provisioning = new PppProvisioningService(
        tap(Mockery::mock(MikrotikPppService::class, [MikrotikServer::default()]), function ($mock): void {
            $mock->shouldReceive('createSecret')->once()->andReturn('.id*1');
        }),
        tap(new RadiusPppService(DB::connection()->getPdo()), fn ($service) => $service->ensureSchema()),
    );

    $prospect = $actor->prospects()->create([
        'name' => 'Pelanggan Auto '.uniqid(),
        'email' => 'auto'.uniqid().'@example.com',
        'whatsapp' => '+6281234567890',
        'address' => 'Jl. Uji',
        'area_code_id' => $area->id,
        'package_id' => $package->id,
        'status' => ProspectStatus::Pending->value,
    ]);

    (new ProspectApprovalService)->approve($prospect, [
        'user_id' => $technician->id,
        'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
    ], $provisioning);

    $ticket = PsbTicket::query()->latest('id')->firstOrFail();
    $ticket->update(['status' => PsbTicketStatus::InProgress->value]);

    actingAsAutomationActor()->post(route('tickets.psb.finish.store', $ticket), [
        'payment_method' => 'tunai',
        'check_pppoe_account' => '1',
        'check_customer_active' => '1',
        'check_vlan_tr069' => '1',
    ])->assertRedirect();

    Bill::create([
        'bill_number' => 'TAG-'.now()->format('Ym').'-0001',
        'psb_ticket_id' => $ticket->id,
        'period' => now()->format('Y-m'),
        'monthly_price' => 124000,
        'dpp' => 111711,
        'ppn' => 12289,
        'total' => 124000,
        'status' => InvoiceStatus::Unpaid->value,
    ]);

    $username = ProspectCredentialService::forProspect($prospect)['username'];
    $secret = PppSecret::query()->where('name', $username)->firstOrFail();

    return [$ticket, $secret, $prospect];
}

test('generate command creates bills once and records the period', function () {
    automationFixture();

    $this->artisan('billing:generate')->assertSuccessful();

    expect(Bill::count())->toBe(1);
    expect(BillingSetting::current()->last_generated_period)->toBe(now()->format('Y-m'));

    // Second run is a no-op.
    $this->artisan('billing:generate')->assertSuccessful();

    expect(Bill::count())->toBe(1);
});

test('auto isolate moves overdue customer to the isolir profile', function () {
    BillingSetting::current()->update(['isolir_day' => now()->day, 'isolir_hour' => '00:00']);

    [$ticket, $secret] = automationFixture();

    $mikrotik = Mockery::mock(MikrotikPppService::class, [MikrotikServer::default()]);
    $mikrotik->shouldReceive('updateSecret')
        ->once()
        ->with($secret->name, ['profile' => 'Isolir']);
    $this->instance(MikrotikPppService::class, $mikrotik);

    $this->artisan('billing:auto-isolate')->assertSuccessful();

    $ticket->refresh();

    expect($ticket->isolated_at)->not->toBeNull();
    expect($ticket->isolated_profile)->toBe('PPPoE-10M');

    // Original profile remembered for restoration on payment.
    expect($ticket->isolated_profile)->toBe('PPPoE-10M');
});

test('marking the bill paid re-enables the isolated customer', function () {
    BillingSetting::current()->update(['isolir_day' => now()->day, 'isolir_hour' => '00:00']);

    [$ticket, $secret] = automationFixture();

    $mikrotik = Mockery::mock(MikrotikPppService::class, [MikrotikServer::default()]);
    $mikrotik->shouldReceive('updateSecret')
        ->twice()
        ->with($secret->name, Mockery::on(fn ($arg) => isset($arg['profile'])));
    $this->instance(MikrotikPppService::class, $mikrotik);

    $this->artisan('billing:auto-isolate')->assertSuccessful();
    expect($ticket->fresh()->isolated_at)->not->toBeNull();

    $bill = Bill::query()->sole();

    actingAsAutomationActor()
        ->post(route('billing.bill.mark-paid', $bill))
        ->assertRedirect();

    expect($bill->fresh()->status)->toBe(InvoiceStatus::Paid);
    expect($ticket->fresh()->isolated_at)->toBeNull();
    expect($ticket->fresh()->isolated_profile)->toBeNull();

});

test('reminder command sends whatsapp on h3 via waha', function () {
    BillingSetting::current()->update([
        'isolir_day' => now()->addDays(3)->day,
        'remind_h3' => true,
        'remind_h2' => false,
        'remind_h1' => false,
    ]);

    automationFixture();

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

    dump('bills sebelum remind:', Bill::query()->get(['id', 'status', 'period', 'reminder_sent_at'])->toArray());

    Artisan::call('billing:remind');
    dump(Artisan::output());

    $bill = Bill::query()->sole();

    expect($bill->reminder_sent_at)->not->toBeNull();
});
