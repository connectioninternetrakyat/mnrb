<?php

use App\Enums\InvoiceStatus;
use App\Enums\ProspectStatus;
use App\Enums\PsbTicketStatus;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\AreaCode;
use App\Models\Invoice;
use App\Models\MessageTemplate;
use App\Models\MidtransAccount;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\PppProfile;
use App\Models\PsbTicket;
use App\Models\User;
use App\Models\WahaServer;
use App\Services\Ppp\MikrotikPppService;
use App\Services\Ppp\PppProvisioningService;
use App\Services\Ppp\RadiusPppService;
use App\Services\Prospect\ProspectApprovalService;
use Database\Seeders\SuperSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(SuperSeeder::class);
});

function ticketActor(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));
}

function ticketTechnician(): User
{
    return tap(User::factory()->create(['name' => 'Teknisi Uji']), fn (User $user) => $user->assignRole(UserRole::Teknisi->value));
}

function ticketFixture(): array
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

    return [$area, $profile, $package];
}

function ticketRadius(): RadiusPppService
{
    $service = new RadiusPppService(DB::connection()->getPdo());
    $service->ensureSchema();

    return $service;
}

/**
 * Create an approved prospect together with its PSB ticket.
 */
function approvedProspectWithTicket(User $actor, array $area, Package $package): PsbTicket
{
    $technician = ticketTechnician();

    $provisioning = new PppProvisioningService(
        tap(Mockery::mock(MikrotikPppService::class, [MikrotikServer::default()]), function ($mock): void {
            $mock->shouldReceive('createSecret')->once()->andReturn('.id*1');
        }),
        ticketRadius(),
    );

    $prospect = $actor->prospects()->create([
        'name' => 'Nadia Putri',
        'email' => 'nadia@example.com',
        'whatsapp' => '+6281234567890',
        'address' => 'Jl. Merdeka No. 10, Tegal',
        'area_code_id' => $area['id'],
        'package_id' => $package->id,
        'status' => ProspectStatus::Pending->value,
    ]);

    (new ProspectApprovalService)->approve($prospect, [
        'user_id' => $technician->id,
        'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
    ], $provisioning);

    return PsbTicket::query()->sole();
}

test('guests are redirected to login and outsiders are forbidden', function () {
    $this->get(route('tickets.psb.index'))->assertRedirect(route('login'));

    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get(route('tickets.psb.index'))->assertForbidden();
});

test('teknisi can view and work tickets while marketing can only view', function () {
    $this->actingAs(ticketTechnician())
        ->get(route('tickets.psb.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('tickets/psb/index'));

    expect(ticketTechnician()->can(UserPermission::TicketsUpdate->value))->toBeTrue();

    $marketing = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::Marketing->value));

    expect($marketing->can(UserPermission::TicketsView->value))->toBeTrue();
    expect($marketing->can(UserPermission::TicketsUpdate->value))->toBeFalse();
});

test('approving a prospect creates a scheduled psb ticket automatically', function () {
    [$area, , $package] = ticketFixture();
    $actor = ticketActor();

    $ticket = approvedProspectWithTicket($actor, $area->toArray(), $package);

    expect($ticket->status)->toBe(PsbTicketStatus::Scheduled);
    expect($ticket->ticket_number)->toMatch('/^PSB-\d{8}-\d{4}$/');
    expect($ticket->assignedTo->name)->toBe('Teknisi Uji');
    expect($ticket->scheduled_at->isFuture())->toBeTrue();
    expect($ticket->prospect->fresh()->status)->toBe(ProspectStatus::Approved);

    // The secret was provisioned for real on radius.
    expect(DB::table('radcheck')->where('username', '05_nadiaputri')->count())->toBe(1);
});

test('ticket index paginates ten per page and filters', function () {
    [$area, , $package] = ticketFixture();
    $actor = ticketActor();

    foreach (range(1, 12) as $i) {
        $actor->prospects()->create([
            'name' => "Pelanggan-{$i}",
            'email' => "p{$i}@example.com",
            'whatsapp' => '+6281234567890',
            'address' => 'Jl. Uji',
            'area_code_id' => $area->id,
            'package_id' => $package->id,
            'status' => ProspectStatus::Approved->value,
            'approved_at' => now(),
        ]);

        PsbTicket::create([
            'ticket_number' => sprintf('PSB-%s-%04d', now()->format('Ymd'), $i),
            'prospect_id' => $actor->prospects()->where('name', "Pelanggan-{$i}")->first()->id,
            'user_id' => ticketTechnician()->id,
            'scheduled_at' => now()->addDay(),
            'status' => PsbTicketStatus::Scheduled->value,
        ]);
    }

    $this->actingAs($actor)
        ->get(route('tickets.psb.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('tickets/psb/index')
                ->has('tickets.data', 10)
                ->where('tickets.total', 12)
        );

    $this->actingAs($actor)
        ->get(route('tickets.psb.index', ['search' => 'Pelanggan-11']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1));

    $this->actingAs($actor)
        ->get(route('tickets.psb.index', ['status' => 'selesai']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 0));
});

test('progress action starts the ticket without requiring a note', function () {
    [$area, , $package] = ticketFixture();
    $actor = ticketActor();
    $ticket = approvedProspectWithTicket($actor, $area->toArray(), $package);

    $this->actingAs($actor)
        ->post(route('tickets.psb.progress.store', $ticket))
        ->assertRedirect(route('tickets.psb.show', $ticket));

    $ticket->refresh();

    expect($ticket->status)->toBe(PsbTicketStatus::InProgress);
    expect($ticket->started_at)->not->toBeNull();

    // Closed tickets cannot be re-started.
    $ticket->update(['status' => PsbTicketStatus::Cancelled->value]);

    $this->actingAs($actor)
        ->post(route('tickets.psb.progress.store', $ticket))
        ->assertRedirect();

    expect($ticket->fresh()->status)->toBe(PsbTicketStatus::Cancelled);
});

test('finish requires in-progress status then closes the ticket', function () {
    [$area, , $package] = ticketFixture();
    $actor = ticketActor();
    $ticket = approvedProspectWithTicket($actor, $area->toArray(), $package);

    // Scheduled tickets cannot be finished directly.
    $this->actingAs($actor)
        ->post(route('tickets.psb.finish.store', $ticket), [
            'finish_note' => 'Instalasi selesai.',
        ])
        ->assertRedirect();

    expect($ticket->fresh()->status)->toBe(PsbTicketStatus::Scheduled);

    $ticket->update(['status' => PsbTicketStatus::InProgress->value]);

    $this->actingAs($actor)
        ->get(route('tickets.psb.finish', $ticket))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('tickets/psb/finish')
                ->where('estimate.monthly_price', 150000)
                ->has('estimate.prorata')
                ->has('estimate.cash_total')
        );

    // Payment method + verification checklist are mandatory on closing.
    $this->actingAs($actor)
        ->post(route('tickets.psb.finish.store', $ticket), [
            'finish_note' => 'Instalasi selesai, internet teruji.',
        ])
        ->assertSessionHasErrors(['payment_method']);

    $this->actingAs($actor)
        ->post(route('tickets.psb.finish.store', $ticket), [
            'finish_note' => 'Instalasi selesai, internet teruji.',
            'payment_method' => 'tunai',
        ])
        ->assertSessionHasErrors(['check_pppoe_account', 'check_customer_active', 'check_vlan_tr069']);

    $this->actingAs($actor)
        ->post(route('tickets.psb.finish.store', $ticket), [
            'payment_method' => 'transfer',
            'bank' => 'bri',
            'check_pppoe_account' => '1',
            'check_customer_active' => '1',
            'check_vlan_tr069' => '1',
        ])
        ->assertRedirect(route('tickets.psb.show', $ticket));

    $ticket->refresh();

    expect($ticket->status)->toBe(PsbTicketStatus::Completed);
    expect($ticket->finished_at)->not->toBeNull();
    expect($ticket->payment_method)->toBe('transfer');
    expect($ticket->check_pppoe_account)->toBeTrue();
    expect($ticket->check_customer_active)->toBeTrue();
    expect($ticket->check_vlan_tr069)->toBeTrue();

    // Transfer -> invoice waits for payment with a 16-digit VA.
    $invoice = Invoice::query()->sole();

    expect($invoice->status)->toBe(InvoiceStatus::Unpaid);
    expect($invoice->paid_at)->toBeNull();
    expect($invoice->bank)->toBe('bri');
    expect($invoice->va_number)->toMatch('/^88808\d{11}$/');
    expect(strlen((string) $invoice->va_number))->toBe(16);
});

test('cash closing is automatically paid in full', function () {
    [$area, , $package] = ticketFixture();
    $actor = ticketActor();
    $ticket = approvedProspectWithTicket($actor, $area->toArray(), $package);

    $ticket->update(['status' => PsbTicketStatus::InProgress->value]);

    $this->actingAs($actor)
        ->post(route('tickets.psb.finish.store', $ticket), [
            'payment_method' => 'tunai',
            'check_pppoe_account' => '1',
            'check_customer_active' => '1',
            'check_vlan_tr069' => '1',
        ])
        ->assertRedirect(route('tickets.psb.show', $ticket));

    $invoice = Invoice::query()->sole();

    expect($invoice->status)->toBe(InvoiceStatus::Paid);
    expect($invoice->paid_at)->not->toBeNull();
});

test('finishing a ticket sends a whatsapp completion notice', function () {
    [$area, , $package] = ticketFixture();
    $actor = ticketActor();
    $ticket = approvedProspectWithTicket($actor, $area->toArray(), $package);

    expect(MessageTemplate::where('slug', 'selesai')->exists())->toBeTrue();

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

    $ticket->update(['status' => PsbTicketStatus::InProgress->value]);

    $this->actingAs($actor)
        ->post(route('tickets.psb.finish.store', $ticket), [
            'payment_method' => 'tunai',
            'check_pppoe_account' => '1',
            'check_customer_active' => '1',
            'check_vlan_tr069' => '1',
        ])
        ->assertRedirect(route('tickets.psb.show', $ticket));

    $invoice = Invoice::query()->sole();

    Http::assertSent(function ($request) use ($invoice, $ticket) {
        return str_contains((string) ($request['chatId'] ?? ''), '6281234567890@c.us')
            && str_contains((string) ($request['text'] ?? ''), $ticket->ticket_number)
            && str_contains((string) ($request['text'] ?? ''), $invoice->invoice_number)
            && str_contains((string) ($request['text'] ?? ''), 'SELESAI')
            && str_contains((string) ($request['text'] ?? ''), 'Nadia Putri');
    });
});

test('transfer invoice is verified automatically by the midtrans webhook', function () {
    [$area, , $package] = ticketFixture();
    $actor = ticketActor();
    $ticket = approvedProspectWithTicket($actor, $area->toArray(), $package);
    $ticket->update(['status' => PsbTicketStatus::InProgress->value]);

    MidtransAccount::create([
        'name' => 'Akun Uji',
        'is_active' => true,
        'is_production' => false,
        'merchant_id' => 'M001',
        'server_key' => 'server-key-uji',
        'client_key' => 'client-key-uji',
    ]);

    $this->actingAs($actor)
        ->post(route('tickets.psb.finish.store', $ticket), [
            'payment_method' => 'transfer',
            'bank' => 'bni',
            'check_pppoe_account' => '1',
            'check_customer_active' => '1',
            'check_vlan_tr069' => '1',
        ])
        ->assertRedirect();

    $invoice = Invoice::query()->sole();

    expect($invoice->status)->toBe(InvoiceStatus::Unpaid);

    // Customer pays the VA; Midtrans notifies the panel.
    $payload = [
        'order_id' => $invoice->invoice_number,
        'status_code' => '200',
        'gross_amount' => (string) $invoice->total_exact.'.00',
        'transaction_status' => 'settlement',
        'fraud_status' => 'accept',
        'signature_key' => hash(
            'sha512',
            $invoice->invoice_number.'200'.(string) $invoice->total_exact.'.00'.'server-key-uji',
        ),
    ];

    $this->postJson('/webhooks/midtrans', $payload)->assertOk();

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Paid);
    expect($invoice->paid_at)->not->toBeNull();
});

test('cancel is impossible without a reason and closed tickets cannot change', function () {
    [$area, , $package] = ticketFixture();
    $actor = ticketActor();
    $ticket = approvedProspectWithTicket($actor, $area->toArray(), $package);

    $this->actingAs($actor)
        ->post(route('tickets.psb.cancel', $ticket), ['cancel_reason' => ''])
        ->assertSessionHasErrors(['cancel_reason']);

    $this->actingAs($actor)
        ->post(route('tickets.psb.cancel', $ticket), [
            'cancel_reason' => 'Pelanggan membatalkan pemasangan.',
        ])
        ->assertRedirect(route('tickets.psb.show', $ticket));

    $ticket->refresh();

    expect($ticket->status)->toBe(PsbTicketStatus::Cancelled);
    expect($ticket->cancelled_at)->not->toBeNull();

    // Cancelled ticket is terminal: no more progress or re-cancel changes.
    $this->actingAs($actor)
        ->post(route('tickets.psb.progress.store', $ticket), [
            'progress_note' => 'Coba lanjut lagi.',
        ])
        ->assertRedirect();

    expect($ticket->fresh()->status)->toBe(PsbTicketStatus::Cancelled);
});

test('detail page shows prospect data and the provisioned secret', function () {
    [$area, , $package] = ticketFixture();
    $actor = ticketActor();
    $ticket = approvedProspectWithTicket($actor, $area->toArray(), $package);

    $this->actingAs($actor)
        ->get(route('tickets.psb.show', $ticket))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('tickets/psb/detail')
                ->where('ticket.ticket_number', $ticket->ticket_number)
                ->where('ticket.prospect_name', 'Nadia Putri')
                ->where('ticket.secret_username', '05_nadiaputri')
                ->where('ticket.secret_password', '14nadiaputri')
                ->where('ticket.profile_name', 'PPPoE-10M')
        );
});
