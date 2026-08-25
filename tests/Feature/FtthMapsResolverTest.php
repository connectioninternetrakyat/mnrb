<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\SuperSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(SuperSeeder::class);

    $this->actor = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));

    // The real short link shared by the owner resolves to a search URL
    // that embeds the coordinates in its path.
    $this->resolvedUrl = 'https://www.google.com/maps/search/-7.154157,+108.333915?entry=tts&g_ep=EgoyMDI2MDgxOS4wIPu8ASoASAFQAw%3D%3D&skid=4485d939-fdb8-48f9-a8d9-093f5ca773f3';
});

test('guests cannot resolve maps urls', function () {
    $this->postJson(route('ftth.maps.resolve'), ['url' => 'https://maps.app.goo.gl/abc'])
        ->assertUnauthorized();
});

test('a maps short link is resolved into precise coordinates', function () {
    Http::fake([
        'https://maps.app.goo.gl/*' => Http::response('', 302, [
            'Location' => $this->resolvedUrl,
        ]),
    ]);

    $response = $this->actingAs($this->actor)
        ->postJson(route('ftth.maps.resolve'), [
            'url' => 'https://maps.app.goo.gl/aBJU5xj4DoNu5v637',
        ]);

    $response->assertOk()
        ->assertJson([
            'latitude' => -7.154157,
            'longitude' => 108.333915,
        ]);
});

test('a multi hop short link chain is followed until coordinates are found', function () {
    Http::fake([
        'https://goo.gl/*' => Http::response('', 302, [
            'Location' => 'https://maps.app.goo.gl/second-hop',
        ]),
        'https://maps.app.goo.gl/*' => Http::response('', 302, [
            'Location' => $this->resolvedUrl,
        ]),
    ]);

    $this->actingAs($this->actor)
        ->postJson(route('ftth.maps.resolve'), [
            'url' => 'https://goo.gl/maps/first',
        ])
        ->assertOk()
        ->assertJson([
            'latitude' => -7.154157,
            'longitude' => 108.333915,
        ]);
});

test('a full place url with at coordinates is parsed directly', function () {
    Http::fake(); // must not hit the network

    $this->actingAs($this->actor)
        ->postJson(route('ftth.maps.resolve'), [
            'url' => 'https://www.google.com/maps/place/Foo/@-7.1541570,108.3339150,17z',
        ])
        ->assertOk()
        ->assertJson([
            'latitude' => -7.154157,
            'longitude' => 108.333915,
        ]);
});

test('a query param url with encoded comma is parsed', function () {
    Http::fake();

    $this->actingAs($this->actor)
        ->postJson(route('ftth.maps.resolve'), [
            'url' => 'https://www.google.com/maps?q=-6.2088%2C106.8456&z=17',
        ])
        ->assertOk()
        ->assertJson([
            'latitude' => -6.2088,
            'longitude' => 106.8456,
        ]);
});

test('an unresolvable url returns a validation error', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><body>No map here</body></html>', 200),
    ]);

    $this->actingAs($this->actor)
        ->postJson(route('ftth.maps.resolve'), [
            'url' => 'https://example.com/no-coordinates',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['url']);
});

test('an unreachable host returns a validation error', function () {
    Http::fake([
        'unreachable.test/*' => Http::failedConnection(),
    ]);

    $this->actingAs($this->actor)
        ->postJson(route('ftth.maps.resolve'), [
            'url' => 'https://unreachable.test/abc',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['url']);
});
