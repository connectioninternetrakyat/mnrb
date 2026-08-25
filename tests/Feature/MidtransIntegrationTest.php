<?php

use App\Enums\InvoiceStatus;
use App\Models\AreaCode;
use App\Models\Invoice;
use App\Models\MidtransAccount;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\PppProfile;
use App\Models\PsbTicket;
use App\Services\Billing\MidtransVaService;
use Database\Seeders\SuperSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(SuperSeeder::class);
});

function midtransActiveAccount(): MidtransAccount
{
    return MidtransAccount::create([
        'name' => 'Midtrans Sandbox',
        'is_active' => true,
        'is_production' => false,
        'merchant_id' => 'G123',
        'server_key' => 'SB-Mid-server-test',
        'client_key' => 'SB-Mid-client-test',
    ]);
}

function midtransInvoiceFixture(): Invoice
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

    $prospectId = DB::table('prospects')->insertGetId([
        'name' => 'Nadia Putri',
        'email' => 'nadia@example.com',
        'whatsapp' => '+6281234567890',
        'address' => 'Jl. Merdeka No. 10',
        'area_code_id' => $area->id,
        'package_id' => Package::firstOrCreate(['name' => 'Paket Home 10M'], [
            'ppp_profile_id' => $profile->id,
            'price' => 124000,
        ])->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ticket = PsbTicket::firstOrCreate(['ticket_number' => 'PSB-MT-0001'], [
        'prospect_id' => $prospectId,
        'scheduled_at' => now()->addDay(),
        'status' => PsbTicket::query()->first()?->status ?? 'terjadwal',
    ]);

    return Invoice::firstOrCreate(['invoice_number' => 'INV-MT-0001'], [
        'psb_ticket_id' => $ticket->id,
        'payment_method' => 'transfer',
        'bank' => 'bri',
        'va_number' => '8880000000001',
        'monthly_price' => 124000,
        'period_days' => 31,
        'active_days' => 31,
        'prorata' => 124000,
        'otc_fee' => 0,
        'promo_percent' => 0,
        'discount' => 0,
        'dpp' => 111711,
        'ppn' => 12289,
        'total_exact' => 124000,
        'cash_total' => 124000,
        'status' => InvoiceStatus::Unpaid->value,
    ]);
}

test('va service charges a real midtrans virtual account', function () {
    $account = midtransActiveAccount();
    $invoice = midtransInvoiceFixture();

    Http::fake([
        '*' => Http::response([
            'status_code' => '201',
            'transaction_status' => 'pending',
            'va_numbers' => [['va_number' => '8808123456789000']],
        ], 201),
    ]);

    $va = (new MidtransVaService)->charge($account, $invoice, 'bri', 'Nadia Putri', '+6281234567890');

    expect($va['va_number'])->toBe('8808123456789000');

    // The charge carried the real order id, amount and server key auth.
    Http::assertSent(function ($request) use ($account, $invoice) {
        $expectedAuth = 'Basic '.base64_encode($account->server_key.':');

        return str_contains($request->url(), '/v2/charge')
            && ($request['transaction_details']['order_id'] ?? null) === $invoice->invoice_number
            && (int) ($request['transaction_details']['gross_amount'] ?? 0) === 124000
            && ($request['bank_transfer']['bank'] ?? null) === 'bri'
            && ($request->header('Authorization')[0] ?? null) === $expectedAuth;
    });
});

test('unsupported bank is rejected by the va service', function () {
    $this->expectException(RuntimeException::class);

    (new MidtransVaService)->charge(
        midtransActiveAccount(),
        midtransInvoiceFixture(),
        'jenius',
        'Nadia Putri',
        null,
    );
});

test('webhook settles a charged transfer invoice end to end', function () {
    $account = midtransActiveAccount();
    $invoice = midtransInvoiceFixture();

    expect($invoice->status)->toBe(InvoiceStatus::Unpaid);

    $gross = (string) $invoice->total_exact.'.00';

    $payload = [
        'order_id' => $invoice->invoice_number,
        'status_code' => '200',
        'gross_amount' => $gross,
        'transaction_status' => 'settlement',
        'fraud_status' => 'accept',
        'signature_key' => hash('sha512', $invoice->invoice_number.'200'.$gross.$account->server_key),
    ];

    test()->postJson('/webhooks/midtrans', $payload)->assertOk();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('gateway failure surfaces as runtime exception with fallback kept', function () {
    midtransActiveAccount();
    $invoice = midtransInvoiceFixture();

    Http::fake([
        '*' => Http::response(['status_code' => '500', 'status_message' => 'internal'], 500),
    ]);

    $service = new MidtransVaService;

    $service->charge($account = midtransActiveAccount(), $invoice, 'bri', 'Nadia Putri', '+6281234567890');
})->throws(RuntimeException::class);

use RuntimeException;
