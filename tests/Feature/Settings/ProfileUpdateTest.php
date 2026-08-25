<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'whatsapp' => '+6281234567890',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->whatsapp)->toBe('+6281234567890');
    expect($user->email_verified_at)->toBeNull();
});

test('whatsapp number is optional and nullable', function () {
    $user = User::factory()->create(['whatsapp' => '081234567890']);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'whatsapp' => '',
        ]);

    $response->assertSessionHasNoErrors();

    expect($user->refresh()->whatsapp)->toBeNull();
});

test('whatsapp number must be valid', function (string $whatsapp) {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'whatsapp' => $whatsapp,
        ]);

    $response->assertSessionHasErrors('whatsapp');
})->with([
    'letters' => 'not-a-number',
    'too short' => '+6212345',
    'special characters' => '+62812#34567',
]);

test('avatar can be uploaded', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

    $response->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->avatar)->not->toBeNull();
    Storage::disk('public')->assertExists($user->avatar);
    expect($user->avatar_url)->toBe(Storage::disk('public')->url($user->avatar));
});

test('replacing the avatar deletes the old file', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $oldAvatarPath = UploadedFile::fake()->image('old.png')->store('avatars', 'public');

    $user->update(['avatar' => $oldAvatarPath]);

    $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('new.png'),
        ]);

    Storage::disk('public')->assertMissing($oldAvatarPath);
    Storage::disk('public')->assertExists($user->refresh()->avatar);
});

test('avatar can be removed', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $avatarPath = UploadedFile::fake()->image('avatar.png')->store('avatars', 'public');

    $user->update(['avatar' => $avatarPath]);

    $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'remove_avatar' => '1',
        ]);

    Storage::disk('public')->assertMissing($avatarPath);

    expect($user->refresh()->avatar)->toBeNull();
});

test('avatar must be a valid image', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->create('document.pdf'),
        ]);

    $response->assertSessionHasErrors('avatar');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
