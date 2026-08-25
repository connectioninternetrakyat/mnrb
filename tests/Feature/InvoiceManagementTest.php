<?php

use App\Enums\InvoiceStatus;
use App\Enums\ProspectStatus;
use App\Enums\PsbTicketStatus;
use App\Enums\UserRole;
use App\Models\AreaCode;
use App\Models\Invoice;
use App\Models\MidtransAccount;
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

function invoiceActor(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));
}

/**
 * Create an in-progress PSB ticket ready to be finished.
 *
 * @return array{0: User, 1: PsbTicket}
 */
function invoiceReadyTicket(string $customerName = 'Nadia Putri'): array
{
    MikrotikServer::firstOrCreate([
        'name' => 'RTR-Uji',
    ], [
        'host' => '127.0.0.1',
        'port' => 1,
        'username' => 'admin',
        'password' => 'x',
        'is_default' => true,
        'timeout' => 1,
    ]);

    $area = AreaCode::firstOrCreate([
        'code' => '05',
    ], [
        'name' => 'Tegal Kota',
    ]);
    $profile = PppProfile::firstOrCreate([
        'mikrotik_server_id' => 1,
        'name' => 'PPPoE-10M',
    ], [
        'rate_limit' => '10M/10M',
    ]);
    $package = Package::firstOrCreate([
        'name' => 'Paket Home 10M',
    ], [
        'ppp_profile_id' => $profile->id,
        'price' => 150000,
    ]);

    $actor = invoiceActor();
    $technician = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::Teknisi->value));

    $provisioning = new PppProvisioningService(
        tap(Mockery::mock(MikrotikPppService::class, [MikrotikServer::default()]), function ($mock): void {
            $mock->shouldReceive('createSecret')->once()->andReturn('.id*1');
        }),
        tap(new RadiusPppService(DB::connection()->getPdo()), fn ($service) => $service->ensureSchema()),
    );

    (new ProspectApprovalService)->approve($actor->prospects()->create([
        'name' => $customerName,
        'email' => 'nadia@example.com',
        'whatsapp' => '+6281234567890',
        'address' => 'Jl. Merdeka No. 10, Tegal',
        'area_code_id' => $area->id,
        'package_id' => $package->id,
        'status' => ProspectStatus::Pending->value,
    ]), [
        'user_id' => $technician->id,
        'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
    ], $provisioning);

    $ticket = PsbTicket::query()->latest('id')->firstOrFail();
    $ticket->update(['status' => PsbTicketStatus::InProgress->value]);

    return [$actor, $ticket];
}

/**
 * Close the ticket as finished with the given payment method.
 */
function finishTicket(PsbTicket $ticket, string $method, ?string $bank = null): void
{
    actingAsInvoiceActor()->post(route('tickets.psb.finish.store', $ticket), [
        'payment_method' => $method,
        'bank' => $bank,
        'check_pppoe_account' => '1',
        'check_customer_active' => '1',
        'check_vlan_tr069' => '1',
    ])->assertRedirect();
}

function actingAsInvoiceActor()
{
    return test()->actingAs(invoiceActor());
}

test('guests are redirected and outsiders forbidden from invoices', function () {
    $this->get(route('billing.invoice.index'))->assertRedirect(route('login'));

    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get(route('billing.invoice.index'))->assertForbidden();
});

test('cash closing issues a paid invoice automatically', function () {
    [$actor, $ticket] = invoiceReadyTicket();

    finishTicket($ticket, 'tunai');

    $invoice = Invoice::query()->sole();

    expect($invoice->status)->toBe(InvoiceStatus::Paid);
    expect($invoice->paid_at)->not->toBeNull();
});

test('transfer closing issues an unpaid invoice with va number', function () {
    [$actor, $ticket] = invoiceReadyTicket();

    finishTicket($ticket, 'transfer', 'bri');

    $invoice = Invoice::query()->sole();

    expect($invoice->status)->toBe(InvoiceStatus::Unpaid);
    expect($invoice->bank)->toBe('bri');
    expect($invoice->va_number)->toMatch('/^88808\d{11}$/');
});

test('superadmin can list invoices with pagination and filters', function () {
    [$actor, $first] = invoiceReadyTicket();
    finishTicket($first, 'tunai');

    // A second unpaid transfer invoice.
    [$actor2, $second] = invoiceReadyTicket('Sinta Rahma');
    finishTicket($second, 'transfer', 'bni');

    $this->actingAs($actor)
        ->get(route('billing.invoice.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('billing/invoices')
                ->has('invoices.data', 2)
                ->where('invoices.total', 2)
        );

    $this->actingAs($actor)
        ->get(route('billing.invoice.index', ['search' => 'Nadia']))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('invoices.data', 1)
                ->where('invoices.data.0.customer_name', 'Nadia Putri')
        );

    $this->actingAs($actor)
        ->get(route('billing.invoice.index', ['status' => 'lunas']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 1));
});

test('mark paid flips only unpaid invoices', function () {
    [$actor, $ticket] = invoiceReadyTicket();
    finishTicket($ticket, 'transfer', 'bca');

    $invoice = Invoice::query()->sole();

    $this->actingAs($actor)
        ->post(route('billing.invoice.mark-paid', $invoice))
        ->assertRedirect();

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Paid);
    expect($invoice->paid_at)->not->toBeNull();

    // Already paid: stays paid without side effects.
    $paidAt = $invoice->paid_at;

    $this->actingAs($actor)
        ->post(route('billing.invoice.mark-paid', $invoice))
        ->assertRedirect();

    expect($invoice->fresh()->paid_at->equalTo($paidAt))->toBeTrue();
});

test('midtrans webhook verifies transfer invoices automatically', function () {
    MidtransAccount::create([
        'name' => 'Akun Uji',
        'is_active' => true,
        'is_production' => false,
        'merchant_id' => 'M001',
        'server_key' => 'server-key-uji',
        'client_key' => 'client-key-uji',
    ]);

    [$actor, $ticket] = invoiceReadyTicket();
    finishTicket($ticket, 'transfer', 'bni');

    $invoice = Invoice::query()->sole();

    $grossAmount = (string) $invoice->total_exact.'.00';

    $payload = [
        'order_id' => $invoice->invoice_number,
        'status_code' => '200',
        'gross_amount' => $grossAmount,
        'transaction_status' => 'settlement',
        'fraud_status' => 'accept',
        'signature_key' => hash(
            'sha512',
            $invoice->invoice_number.'200'.$grossAmount.'server-key-uji',
        ),
    ];

    $this->postJson('/webhooks/midtrans', $payload)->assertOk();

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Paid);
    expect($invoice->paid_at)->not->toBeNull();

    // Amount mismatch must not flip the second unpaid invoice.
    [$actor2, $ticket2] = invoiceReadyTicket('Bella Anisa');
    finishTicket($ticket2, 'transfer', 'bni');

    $second = Invoice::query()->whereKeyNot($invoice->id)->sole();

    $badPayload = [
        'order_id' => $second->invoice_number,
        'status_code' => '200',
        'gross_amount' => '999.00',
        'transaction_status' => 'settlement',
        'fraud_status' => 'accept',
        'signature_key' => hash(
            'sha512',
            $second->invoice_number.'200'.'999.00'.'server-key-uji',
        ),
    ];

    $this->postJson('/webhooks/midtrans', $badPayload)->assertOk();

    expect($second->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});
