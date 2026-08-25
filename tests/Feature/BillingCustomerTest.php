<?php

use App\Enums\CustomerStatus;
use App\Enums\ProspectStatus;
use App\Enums\PsbTicketStatus;
use App\Enums\UserRole;
use App\Models\AreaCode;
use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\PppProfile;
use App\Models\PppSecret;
use App\Models\PsbTicket;
use App\Models\User;
use App\Services\Ppp\MikrotikPppService;
use Database\Seeders\SuperSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(SuperSeeder::class);
});

function customerActor(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));
}

function customerFixture(): array
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

    return [$area, $profile, $package];
}

function validCustomerPayload(array $area, Package $package): array
{
    return [
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'whatsapp' => '+6281234567890',
        'address' => 'Jl. Merdeka No. 1, Tegal',
        'area_code_id' => $area->id,
        'package_id' => $package->id,
        'provision' => '0',
    ];
}

test('guests are redirected and outsiders forbidden', function () {
    $this->get(route('billing.customer.index'))->assertRedirect(route('login'));

    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get(route('billing.customer.index'))->assertForbidden();
});

test('superadmin can create, update and list customers', function () {
    [$area, , $package] = customerFixture();
    $actor = customerActor();

    $payload = [
        'name' => 'Citra Lestari',
        'email' => 'citra@example.com',
        'whatsapp' => '081234567890',
        'address' => 'Jl. Melati No. 2',
        'area_code_id' => $area->id,
        'package_id' => $package->id,
        'notes' => 'Pelanggan baru',
    ];

    $this->actingAs($actor)
        ->post(route('billing.customer.store'), $payload)
        ->assertRedirect();

    $customer = Customer::query()->sole();

    expect($customer->status)->toBe(CustomerStatus::Active);
    expect($customer->ppp_secret_id)->toBeNull();

    // Update.
    $this->actingAs($actor)
        ->put(route('billing.customer.update', $customer), [
            ...$payload,
            'name' => 'Citra Lestari Update',
        ])
        ->assertRedirect();

    expect($customer->fresh()->name)->toBe('Citra Lestari Update');

    // List shows the customer with pagination metadata.
    $this->actingAs($actor)
        ->get(route('billing.customer.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('billing/customers')
                ->where('customers.data.0.name', 'Citra Lestari Update')
        );

    // Delete works for customers without PPP accounts.
    $this->actingAs($actor)->delete(route('billing.customer.destroy', $customer))->assertRedirect();

    expect(Customer::count())->toBe(0);
});

test('store validation requires core fields and letter-first name', function () {
    $this->actingAs(customerActor());

    $this->post(route('billing.customer.store'), [])
        ->assertSessionHasErrors(['name', 'whatsapp', 'address', 'area_code_id', 'package_id']);

    $this->post(route('billing.customer.store'), [
        'name' => '9Nama',
        'whatsapp' => '081234567890',
        'email' => 'x@example.com',
        'address' => '-',
        'area_code_id' => 99999,
        'package_id' => 99999,
    ])->assertSessionHasErrors(['name', 'area_code_id', 'package_id']);
});

test('suspend and activate toggle the real mikrotik secret state', function () {
    [$area, , $package] = customerFixture();
    $actor = customerActor();

    MikrotikServer::default() ?: null;

    $secret = PppSecret::query()->create([
        'mikrotik_server_id' => 1,
        'name' => '05_budisantoso',
        'password' => '2budisantoso',
        'profile' => 'PPPoE-10M',
        'disabled_on_mikrotik' => false,
    ]);

    $customer = Customer::create([
        'name' => 'Budi Santoso',
        'whatsapp' => '+6281234567890',
        'address' => 'Jl. Uji',
        'area_code_id' => $area->id,
        'package_id' => $package->id,
        'ppp_secret_id' => $secret->id,
        'status' => CustomerStatus::Active->value,
    ]);

    $mikrotik = Mockery::mock(MikrotikPppService::class, [MikrotikServer::default()]);
    $mikrotik->shouldReceive('updateSecret')
        ->once()
        ->with('05_budisantoso', ['disabled' => true]);
    $this->instance(MikrotikPppService::class, $mikrotik);

    $this->actingAs($actor)
        ->post(route('billing.customer.suspend', $customer))
        ->assertRedirect();

    expect($customer->fresh()->status)->toBe(CustomerStatus::Suspended);

    $activateMock = Mockery::mock(MikrotikPppService::class, [MikrotikServer::default()]);
    $activateMock->shouldReceive('updateSecret')
        ->once()
        ->with('05_budisantoso', ['disabled' => false]);
    $this->instance(MikrotikPppService::class, $activateMock);

    $this->actingAs($actor)
        ->post(route('billing.customer.activate', $customer))
        ->assertRedirect();

    expect($customer->fresh()->status)->toBe(CustomerStatus::Active);
});

test('upgrade really moves the ppp secret profile on mikrotik and radius', function () {
    [$area, $oldProfile, $oldPackage] = customerFixture();
    $actor = customerActor();

    $newProfile = PppProfile::create([
        'mikrotik_server_id' => 1,
        'name' => 'PPPoE-20M',
        'rate_limit' => '20M/20M',
    ]);
    $newPackage = Package::create([
        'name' => 'Paket Home 20M',
        'ppp_profile_id' => $newProfile->id,
        'price' => 200000,
    ]);

    $secret = PppSecret::query()->create([
        'mikrotik_server_id' => 1,
        'name' => '05_budisantoso',
        'password' => '2budisantoso',
        'profile' => 'PPPoE-10M',
        'disabled_on_mikrotik' => false,
    ]);

    $customer = Customer::create([
        'name' => 'Budi Santoso',
        'whatsapp' => '+6281234567890',
        'address' => 'Jl. Uji',
        'area_code_id' => $area->id,
        'package_id' => $oldPackage->id,
        'ppp_secret_id' => $secret->id,
        'status' => CustomerStatus::Active->value,
    ]);

    $mikrotik = Mockery::mock(MikrotikPppService::class, [MikrotikServer::default()]);
    $mikrotik->shouldReceive('updateSecret')
        ->once()
        ->with('05_budisantoso', ['profile' => 'PPPoE-20M']);
    $this->instance(MikrotikPppService::class, $mikrotik);

    $this->actingAs($actor)
        ->post(route('billing.customer.upgrade', $customer), [
            'package_id' => $newPackage->id,
        ])
        ->assertRedirect();

    $customer->refresh();

    expect($customer->package_id)->toBe($newPackage->id);

});

test('customer detail page shows integrated ticket data for converted customers', function () {
    [$area, , $package] = customerFixture();
    $actor = customerActor();

    $prospect = $actor->prospects()->create([
        'name' => 'Citra Lestari',
        'email' => 'citra@example.com',
        'whatsapp' => '+6281234567890',
        'address' => 'Jl. Melati No. 2',
        'area_code_id' => $area->id,
        'package_id' => $package->id,
        'status' => ProspectStatus::Approved->value,
        'approved_at' => now()->subDay(),
    ]);

    $ticket = PsbTicket::create([
        'ticket_number' => PsbTicket::generateNumber(),
        'prospect_id' => $prospect->id,
        'scheduled_at' => now()->subDay(),
        'finished_at' => now()->subHours(2),
        'status' => PsbTicketStatus::Completed->value,
    ]);

    $customer = Customer::create([
        'name' => 'Citra Lestari',
        'whatsapp' => '+6281234567890',
        'address' => 'Jl. Melati No. 2',
        'area_code_id' => $area->id,
        'package_id' => $package->id,
        'psb_ticket_id' => $ticket->id,
        'status' => CustomerStatus::Active->value,
    ]);

    $this->actingAs($actor)
        ->get(route('billing.customer.show', $customer))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('billing/customers-detail')
                ->where('customer.name', 'Citra Lestari')
                ->where('customer.converted_from_prospect', true)
                ->where('ticket.ticket_number', $ticket->ticket_number)
                ->where('invoice', null)
                ->where('bills', [])
        );
});

test('manual customer detail shows no integration chain', function () {
    [$area, , $package] = customerFixture();
    $actor = customerActor();

    $customer = Customer::create([
        'name' => 'Dewi Manual',
        'whatsapp' => '+6281900000000',
        'address' => 'Jl. Manual No. 1',
        'area_code_id' => $area->id,
        'package_id' => $package->id,
        'status' => CustomerStatus::Active->value,
    ]);

    $this->actingAs($actor)
        ->get(route('billing.customer.show', $customer))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('billing/customers-detail')
                ->where('customer.converted_from_prospect', false)
                ->where('customer.pelanggan_id', null)
                ->where('ticket', null)
                ->where('summary.bills_count', 0)
        );
});
