<?php

use App\Enums\UserRole;
use App\Models\AreaCode;
use App\Models\OdpOdc;
use App\Models\User;
use Database\Seeders\SuperSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(SuperSeeder::class);
});

function ftthActor(): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::SuperAdmin->value));
}

test('guests are redirected to login', function () {
    $this->get(route('ftth.area-codes.index'))->assertRedirect(route('login'));
    $this->get(route('ftth.odp-odc.index'))->assertRedirect(route('login'));
});

test('users without permission cannot open ftth pages', function () {
    $actor = tap(User::factory()->create(), fn (User $user) => $user->assignRole(UserRole::Teknisi->value));

    $this->actingAs($actor)->get(route('ftth.area-codes.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('ftth.odp-odc.index'))->assertForbidden();
});

test('superadmin can manage area codes with full crud', function () {
    $actor = ftthActor();

    $this->actingAs($actor)
        ->get(route('ftth.area-codes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('ftth/area-codes')->has('areas', 0));

    $this->actingAs($actor)
        ->post(route('ftth.area-codes.store'), [
            'code' => 'TCL01',
            'name' => 'Area Talasalak',
            'region' => 'Banjarbaru Selatan',
            'description' => 'Coverage awal FTTH.',
        ])
        ->assertRedirect();

    expect(AreaCode::where('code', 'TCL01')->exists())->toBeTrue();

    $area = AreaCode::where('code', 'TCL01')->firstOrFail();

    $this->actingAs($actor)
        ->put(route('ftth.area-codes.update', $area), [
            'code' => 'TCL01',
            'name' => 'Area Talasalak Timur',
            'region' => 'Banjarbaru Selatan',
            'description' => null,
        ])
        ->assertRedirect();

    expect($area->refresh()->name)->toBe('Area Talasalak Timur');
});

test('area code must be unique', function () {
    $actor = ftthActor();
    AreaCode::create(['code' => 'TCL01', 'name' => 'Existing']);

    $this->actingAs($actor)
        ->post(route('ftth.area-codes.store'), [
            'code' => 'TCL01',
            'name' => 'Duplikat',
        ])
        ->assertSessionHasErrors(['code']);
});

test('an area code still in use cannot be deleted', function () {
    $actor = ftthActor();
    $area = AreaCode::create(['code' => 'TCL01', 'name' => 'Terpakai']);
    OdpOdc::create([
        'code' => 'ODP-TCL01-001',
        'name' => 'ODP Depan Kantor',
        'area_code_id' => $area->id,
    ]);

    $this->actingAs($actor)
        ->delete(route('ftth.area-codes.destroy', $area))
        ->assertForbidden();

    expect(AreaCode::count())->toBe(1);

    $device = OdpOdc::firstOrFail();
    $this->actingAs($actor)
        ->delete(route('ftth.odp-odc.destroy', $device))
        ->assertRedirect();

    expect(OdpOdc::count())->toBe(0);
});

test('superadmin can create an odp with coordinates from a maps url', function () {
    $actor = ftthActor();
    $area = AreaCode::create(['code' => 'TCL01', 'name' => 'Talasalak']);

    $this->actingAs($actor)
        ->post(route('ftth.odp-odc.store'), [
            'code' => 'ODP-TCL01-001',
            'name' => 'ODP Depan Kantor',
            'area_code_id' => $area->id,
            'region' => 'Banjarbaru Selatan',
            'port' => '8',
            'splitter_ratio' => '1:8',
            'maps_url' => 'https://maps.app.goo.gl/aBJU5xj4DoNu5v637',
            'latitude' => '-7.154157',
            'longitude' => '108.333915',
            'description' => 'Menempel di tiang pln dekat gang 3.',
        ])
        ->assertRedirect();

    $odp = OdpOdc::firstOrFail();

    expect($odp->port)->toBe(8);
    expect($odp->splitter_ratio)->toBe('1:8');
    expect($odp->latitude)->toBe(-7.154157);
    expect($odp->longitude)->toBe(108.333915);
    expect($odp->areaCode->code)->toBe('TCL01');
});

test('odp validation rejects bad splitter ratio and out of range coordinates', function () {
    $actor = ftthActor();
    $area = AreaCode::create(['code' => 'TCL01', 'name' => 'Talasalak']);

    $this->actingAs($actor)
        ->post(route('ftth.odp-odc.store'), [
            'code' => 'ODP-X',
            'name' => 'Invalid',
            'area_code_id' => $area->id,
            'port' => '8',
            'splitter_ratio' => '1-8',
            'latitude' => '999',
            'longitude' => '-5000',
        ])
        ->assertSessionHasErrors(['splitter_ratio', 'latitude', 'longitude']);
});
