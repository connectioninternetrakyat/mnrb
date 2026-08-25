<?php

use App\Enums\InvoiceStatus;
use App\Enums\ProspectStatus;
use App\Enums\PsbTicketStatus;
use App\Enums\UserRole;
use App\Models\AreaCode;
use App\Models\BillingSetting;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\PppProfile;
use App\Models\PsBTicket;
use App\Models\User;
use App\Services\Billing\ProratedInvoiceEstimator;
use App\Services\Ppp\MikrotikPppService;
use App\Services\Ppp\PppProvisioningService;
use App\Services\Ppp\RadiusPppService;
use App\Services\Prospect\ProspectApprovalService;
use Database\Seeders\SuperSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(SuperSeeder::class);
});

function billingActor(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));
}

test('guests are redirected and outsiders are forbidden', function () {
    $this->get(route('billing.setting.index'))->assertRedirect(route('login'));

    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get(route('billing.setting.index'))->assertForbidden();
});

test('superadmin sees the settings form with defaults', function () {
    $this->actingAs(billingActor())
        ->get(route('billing.setting.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('billing/settings')
                ->where('settings.prorate_cut_day', 10)
                ->where('settings.ppn_percent', 11)
                ->where('settings.promo_percent', 0)
                ->where('settings.remind_h3', true)
        );
});

test('update validates ranges', function () {
    $this->actingAs(billingActor());

    $this->put(route('billing.setting.update'), [
        'prorate_cut_day' => 40,
        'invoice_day' => 0,
        'isolir_day' => 99,
        'ppn_percent' => -5,
        'promo_percent' => 200,
    ])->assertSessionHasErrors([
        'prorate_cut_day',
        'invoice_day',
        'isolir_day',
        'ppn_percent',
        'promo_percent',
    ]);
});

test('update persists settings including unchecked reminders', function () {
    BillingSetting::current();

    $this->actingAs(billingActor())
        ->put(route('billing.setting.update'), [
            'prorate_cut_day' => 25,
            'invoice_day' => 26,
            'isolir_day' => null,
            'isolir_hour' => '01:00',
            'ppn_percent' => 0,
            'promo_percent' => 20,
            // remind_* absent = unchecked = false.
        ])
        ->assertRedirect();

    $settings = BillingSetting::current()->refresh();

    expect($settings->prorate_cut_day)->toBe(25);
    expect($settings->invoice_day)->toBe(26);
    expect($settings->isolir_day)->toBeNull();
    expect($settings->isolir_hour)->toBe('01:00');
    expect($settings->ppn_percent)->toBe(0.0);
    expect($settings->promo_percent)->toBe(20);
    expect($settings->remind_h3)->toBeFalse();
    expect($settings->remind_h1)->toBeFalse();
});

test('estimator follows the updated settings when closing tickets', function () {
    [$area, $package] = ticketFixtureForBilling();

    BillingSetting::current()->update([
        'prorate_cut_day' => 5,
        'ppn_percent' => 0,
        'promo_percent' => 0,
    ]);

    $actor = billingActor();
    $technician = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::Teknisi->value));

    $provisioning = new PppProvisioningService(
        tap(Mockery::mock(MikrotikPppService::class, [MikrotikServer::default()]), function ($mock): void {
            $mock->shouldReceive('createSecret')->once()->andReturn('.id*1');
        }),
        tap(new RadiusPppService(DB::connection()->getPdo()), fn ($service) => $service->ensureSchema()),
    );

    // Approving the prospect issues the PSB ticket automatically.
    (new ProspectApprovalService)->approve($actor->prospects()->create([
        'name' => 'Pelanggan Tagihan',
        'email' => 'pt@example.com',
        'whatsapp' => '+6281234567890',
        'address' => 'Jl. Uji',
        'area_code_id' => $area->id,
        'package_id' => $package->id,
        'status' => ProspectStatus::Pending->value,
    ]), [
        'user_id' => $technician->id,
        'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
    ], $provisioning);

    $ticket = PsBTicket::query()->sole();

    // Only in-progress tickets can be finished.
    $ticket->update(['status' => PsbTicketStatus::InProgress->value]);

    $this->actingAs($actor)
        ->post(route('tickets.psb.finish.store', $ticket), [
            'payment_method' => 'tunai',
            'check_pppoe_account' => '1',
            'check_customer_active' => '1',
            'check_vlan_tr069' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $estimate = (new ProratedInvoiceEstimator)->estimate(150000, now());

    // With PPN disabled the exact total has no tax split.
    expect($estimate['ppn'])->toBe(0);

    // The issued invoice snapshots whatever the settings computed.
    $invoice = $ticket->invoice;

    expect($invoice)->not->toBeNull();
    expect($invoice->total_exact)->toBe((int) $estimate['total_exact']);
    expect($invoice->status)->toBe(InvoiceStatus::Paid);
});

function ticketFixtureForBilling(): array
{
    MikrotikServer::create([
        'name' => 'RTR-Uji',
        'host' => '127.0.0.1',
        'port' => 1,
        'username' => 'admin',
        'password' => 'x',
        'is_default' => true,
        'timeout' => 1,
    ]);

    $area = AreaCode::create(['code' => '05', 'name' => 'Tegal Kota']);
    $profile = PppProfile::create([
        'mikrotik_server_id' => 1,
        'name' => 'PPPoE-10M',
        'rate_limit' => '10M/10M',
    ]);
    $package = Package::create([
        'name' => 'Paket Home 10M',
        'ppp_profile_id' => $profile->id,
        'price' => 150000,
    ]);

    return [$area, $package];
}
