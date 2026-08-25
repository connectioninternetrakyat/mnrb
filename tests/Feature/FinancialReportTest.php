<?php

use App\Enums\InvoiceStatus;
use App\Enums\PsbTicketStatus;
use App\Enums\UserRole;
use App\Models\AreaCode;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\PppProfile;
use App\Models\PsbTicket;
use App\Models\User;
use Database\Seeders\SuperSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(SuperSeeder::class);
});

function reportActor(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));
}

/**
 * A completed PSB ticket so invoices/bills have something to attach to.
 */
function reportTicket(): PsbTicket
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

    static $sequence = 0;
    $sequence++;

    return PsbTicket::firstOrCreate([
        'ticket_number' => 'PSB-REP-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
    ], [
        'prospect_id' => DB::table('prospects')->insertGetId([
            'name' => "Pelanggan {$sequence}",
            'email' => "p{$sequence}@example.com",
            'whatsapp' => '+6281234567890',
            'address' => 'Jl. Uji',
            'area_code_id' => $area->id,
            'package_id' => $package->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'scheduled_at' => now()->subDays(7),
        'status' => PsbTicketStatus::Completed->value,
    ]);
}

function paidInvoice(int $total, ?string $paidAt = null): Invoice
{
    return Invoice::create([
        'invoice_number' => 'INV-'.uniqid(),
        'psb_ticket_id' => reportTicket()->id,
        'payment_method' => 'tunai',
        'monthly_price' => $total,
        'period_days' => 31,
        'active_days' => 31,
        'prorata' => $total,
        'otc_fee' => 0,
        'promo_percent' => 0,
        'discount' => 0,
        'dpp' => $total,
        'ppn' => 0,
        'total_exact' => $total,
        'cash_total' => $total,
        'status' => InvoiceStatus::Paid->value,
        'paid_at' => $paidAt ?? now(),
    ]);
}

function paidBill(int $total, string $period, ?string $paidAt = null): Bill
{
    return Bill::create([
        'bill_number' => 'TAG-'.uniqid(),
        'psb_ticket_id' => reportTicket()->id,
        'period' => $period,
        'monthly_price' => $total,
        'dpp' => $total,
        'ppn' => 0,
        'total' => $total,
        'status' => InvoiceStatus::Paid->value,
        'paid_at' => $paidAt ?? now(),
    ]);
}

test('guests are redirected and outsiders forbidden', function () {
    $this->get(route('billing.report.index'))->assertRedirect(route('login'));

    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get(route('billing.report.index'))->assertForbidden();
});

test('report aggregates real paid invoices and bills per month', function () {
    $year = (int) now()->format('Y');

    // Two activation invoices paid this month.
    paidInvoice(50000);
    paidInvoice(70000);

    // One monthly bill paid this month.
    paidBill(124000, now()->format('Y-m'));

    // Unpaid bill counts toward outstanding only.
    Bill::create([
        'bill_number' => 'TAG-TUNGGAK',
        'psb_ticket_id' => reportTicket()->id,
        'period' => now()->subMonth()->format('Y-m'),
        'monthly_price' => 999999,
        'dpp' => 999999,
        'ppn' => 0,
        'total' => 999999,
        'status' => InvoiceStatus::Unpaid->value,
    ]);

    $this->actingAs(reportActor())
        ->get(route('billing.report.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('billing/reports')
                ->where('year', $year)
                ->where('cards.activation_total', 120000)
                ->where('cards.activation_count', 2)
                ->where('cards.bill_total', 124000)
                ->where('outstanding.total', 999999)
                ->where('outstanding.count', 1)
                ->where('grand.total', 244000)
        );
});

test('payments land in the month they were actually paid', function () {
    $lastYear = (int) now()->subYear()->format('Y');

    // Paid last year: belongs to last year's report, not current.
    paidInvoice(50000, now()->subYear()->startOfMonth()->toDateString());

    $this->actingAs(reportActor())
        ->get(route('billing.report.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('cards.income_total', 0));

    $this->actingAs(reportActor())
        ->get(route('billing.report.index', ['year' => $lastYear]))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('year', $lastYear)
                ->where('grand.invoice_total', 50000)
        );
});

test('yearly csv download streams the report', function () {
    $actor = reportActor();

    paidInvoice(50000);

    $response = $this->actingAs($actor)
        ->get(route('billing.report.download', ['year' => now()->format('Y')]));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv');
});
