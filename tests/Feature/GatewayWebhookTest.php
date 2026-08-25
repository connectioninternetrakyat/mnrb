<?php

use App\Models\MidtransAccount;
use App\Models\WahaServer;
use Database\Seeders\SuperSeeder;

beforeEach(function () {
    $this->seed(SuperSeeder::class);
});

function midtransSignature(array $payload, string $serverKey): string
{
    return hash('sha512', implode('', [
        (string) ($payload['order_id'] ?? ''),
        (string) ($payload['status_code'] ?? ''),
        (string) ($payload['gross_amount'] ?? ''),
        $serverKey,
    ]));
}

test('midtrans webhook accepts a notification matching any active account', function () {
    MidtransAccount::create(['name' => 'A', 'is_active' => true, 'server_key' => 'key-a']);
    $target = MidtransAccount::create(['name' => 'B', 'is_active' => true, 'server_key' => 'key-b']);

    $payload = [
        'order_id' => 'ORDER-123',
        'status_code' => '200',
        'gross_amount' => '145000.00',
        'transaction_status' => 'settlement',
    ];

    $response = $this->postJson(route('webhooks.midtrans'), [
        ...$payload,
        'signature_key' => midtransSignature($payload, 'key-b'),
    ]);

    $response->assertOk();

    expect($target->refresh()->last_notified_at)->not->toBeNull();
    expect(MidtransAccount::where('name', 'A')->firstOrFail()->last_notified_at)->toBeNull();
});

test('midtrans webhook ignores inactive accounts when verifying signatures', function () {
    $inactive = MidtransAccount::create(['name' => 'Nonaktif', 'is_active' => false, 'server_key' => 'key-x']);

    $payload = [
        'order_id' => 'ORDER-1',
        'status_code' => '200',
        'gross_amount' => '10000.00',
    ];

    $this->postJson(route('webhooks.midtrans'), [
        ...$payload,
        'signature_key' => midtransSignature($payload, 'key-x'),
    ])->assertStatus(403);

    expect($inactive->refresh()->last_notified_at)->toBeNull();
});

test('midtrans webhook rejects an invalid signature', function () {
    MidtransAccount::create(['name' => 'A', 'is_active' => true, 'server_key' => 'key-a']);

    $this->postJson(route('webhooks.midtrans'), [
        'order_id' => 'ORDER-123',
        'status_code' => '200',
        'gross_amount' => '145000.00',
        'signature_key' => str_repeat('a', 128),
    ])->assertStatus(403);
});

test('waha webhook accepts an event matching any stored server api key', function () {
    WahaServer::create(['name' => 'S1', 'base_url' => 'https://a.test', 'api_key' => 'key-1']);
    $target = WahaServer::create(['name' => 'S2', 'base_url' => 'https://b.test', 'api_key' => 'key-2']);

    $response = $this->postJson(
        route('webhooks.waha'),
        ['event' => 'message'],
        ['X-Api-Key' => 'key-2'],
    );

    $response->assertOk();

    expect($target->refresh()->last_webhook_at)->not->toBeNull();
    expect(WahaServer::where('name', 'S1')->firstOrFail()->last_webhook_at)->toBeNull();
});

test('waha webhook rejects wrong or missing api keys', function () {
    WahaServer::create(['name' => 'S1', 'base_url' => 'https://a.test', 'api_key' => 'key-1']);

    $this->postJson(route('webhooks.waha'), ['event' => 'message'])
        ->assertStatus(401);

    $this->postJson(
        route('webhooks.waha'),
        ['event' => 'message'],
        ['X-Api-Key' => 'wrong'],
    )->assertStatus(401);

    expect(WahaServer::firstOrFail()->last_webhook_at)->toBeNull();
});
