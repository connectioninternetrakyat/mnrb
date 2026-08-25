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
use App\Models\PsbTicket;
use App\Models\User;
use App\Services\Ppp\MikrotikPppService;
use App\Services\Ppp\PppProvisioningService;
use App\Services\Ppp\RadiusPppService;
use App\Services\Prospect\ProspectApprovalService;
use Database\Seeders\SuperSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(SuperSeeder::class);
});

function billActor(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));
}

/**
 * Create a completed PSB ticket (active customer) with an issued invoice.
 *
 * @return array{0: PsbTicket, 1: Package}
 */
function completedTicket(): PsbTicket
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
    $package = Package::firstOrCreate([
        'name' => 'Paket Home 10M',
    ], [
        'ppp_profile_id' => $profile->id,
        'price' => 124000,
    ]);

    $actor = billActor();
    $technician = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::Teknisi->value));

    $provisioning = new PppProvisioningService(
        tap(Mockery::mock(MikrotikPppService::class, [MikrotikServer::default()]), function ($mock): void {
            $mock->shouldReceive('createSecret')->once()->andReturn('.id*1');
        }),
        tap(new RadiusPppService(DB::connection()->getPdo()), fn ($service) => $service->ensureSchema()),
    );

    $prospect = $actor->prospects()->create([
        'name' => 'Pelanggan '.uniqid(),
        'email' => 'c'.uniqid().'@example.com',
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

    actingAsBillActor()->post(route('tickets.psb.finish.store', $ticket), [
        'payment_method' => 'tunai',
        'check_pppoe_account' => '1',
        'check_customer_active' => '1',
        'check_vlan_tr069' => '1',
    ])->assertRedirect();

    $ticket->refresh();

    return $ticket;
}

function actingAsBillActor()
{
    return test()->actingAs(billActor());
}

test('guests are redirected and outsiders forbidden from bills', function () {
    $this->get(route('billing.bill.index'))->assertRedirect(route('login'));

    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get(route('billing.bill.index'))->assertForbidden();
});

test('generate creates monthly bills for completed tickets without promo', function () {
    BillingSetting::current()->update(['promo_percent' => 45]);

    $ticketA = completedTicket();
    $ticketB = completedTicket();

    $period = now()->format('Y-m');

    $this->actingAs(billActor())
        ->post(route('billing.bill.generate'))
        ->assertRedirect();

    expect(Bill::count())->toBe(2);

    $billA = $ticketA->bills()->where('period', $period)->first();

    // Promo never applies to recurring bills; PPN split follows settings.
    expect($billA->monthly_price)->toBe(124000);
    expect($billA->total)->toBe(124000);
    expect($billA->dpp)->toBe((int) floor(124000 / 1.11));
    expect($billA->ppn)->toBe(124000 - (int) floor(124000 / 1.11));
    expect($billA->status)->toBe(InvoiceStatus::Unpaid);
});

test('generate is idempotent per period', function () {
    completedTicket();

    actingAsBillActor()->post(route('billing.bill.generate'))->assertRedirect();
    actingAsBillActor()->post(route('billing.bill.generate'))->assertRedirect();

    expect(Bill::count())->toBe(1);
});

test('bill index paginates ten per page and filters by status', function () {
    BillingSetting::current()->update(['ppn_percent' => 0]);

    for ($i = 0; $i < 12; $i++) {
        completedTicket();
    }

    actingAsBillActor()->post(route('billing.bill.generate'))->assertRedirect();

    actingAsBillActor()
        ->get(route('billing.bill.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('billing/bills')
                ->has('bills.data', 10)
                ->where('bills.total', 12)
        );

    actingAsBillActor()
        ->get(route('billing.bill.index', ['status' => 'lunas']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('bills.data', 0));
});

test('bill can be marked as paid manually', function () {
    $ticket = completedTicket();

    actingAsBillActor()->post(route('billing.bill.generate'))->assertRedirect();

    $bill = $ticket->bills()->firstOrFail();

    $this->actingAs(billActor())
        ->post(route('billing.bill.mark-paid', $bill))
        ->assertRedirect();

    $bill->refresh();

    expect($bill->status)->toBe(InvoiceStatus::Paid);
    expect($bill->paid_at)->not->toBeNull();
});
