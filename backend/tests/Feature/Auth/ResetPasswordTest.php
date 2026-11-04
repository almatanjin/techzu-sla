<?php

use App\Models\User;
use Illuminate\Support\Facades\Password;

it('resets password with valid token, updates login credentials, and revokes all active tokens', function () {
    $user = User::factory()->customer()->create([
        'email' => 'resetme@example.test',
        'password' => 'OldPassword123',
    ]);

    $tokenA = $user->createToken('device-a')->plainTextToken;
    $tokenB = $user->createToken('device-b')->plainTextToken;

    $resetToken = Password::broker()->createToken($user);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'email' => 'RESETME@EXAMPLE.TEST',
        'token' => $resetToken,
        'password' => 'NewPassword999',
        'password_confirmation' => 'NewPassword999',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message']);

    // New password works for login
    $this->postJson('/api/v1/auth/login', [
        'email' => 'resetme@example.test',
        'password' => 'NewPassword999',
    ])->assertOk();

    // Old password fails
    $this->postJson('/api/v1/auth/login', [
        'email' => 'resetme@example.test',
        'password' => 'OldPassword123',
    ])->assertStatus(422);

    $this->app['auth']->forgetGuards();

    // Both previous tokens now return 401
    $this->withToken($tokenA)
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();

    $this->app['auth']->forgetGuards();

    $this->withToken($tokenB)
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

it('rejects an invalid reset token', function () {
    $user = User::factory()->customer()->create([
        'email' => 'wrongtoken@example.test',
        'password' => 'OldPassword123',
    ]);

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'email' => $user->email,
        'token' => 'completely-invalid-token',
        'password' => 'NewPassword999',
        'password_confirmation' => 'NewPassword999',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    // Password unchanged
    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'OldPassword123',
    ])->assertOk();
});

it('rejects a reused reset token', function () {
    $user = User::factory()->customer()->create([
        'email' => 'reused@example.test',
        'password' => 'OldPassword123',
    ]);

    $resetToken = Password::broker()->createToken($user);

    // First use succeeds
    $this->postJson('/api/v1/auth/reset-password', [
        'email' => $user->email,
        'token' => $resetToken,
        'password' => 'NewPassword111',
        'password_confirmation' => 'NewPassword111',
    ])->assertOk();

    // Second use with same token fails
    $response = $this->postJson('/api/v1/auth/reset-password', [
        'email' => $user->email,
        'token' => $resetToken,
        'password' => 'AnotherPassword222',
        'password_confirmation' => 'AnotherPassword222',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('rejects an expired reset token after 61 minutes', function () {
    $user = User::factory()->customer()->create([
        'email' => 'expired@example.test',
        'password' => 'OldPassword123',
    ]);

    $resetToken = Password::broker()->createToken($user);

    $this->travel(61)->minutes();

    $response = $this->postJson('/api/v1/auth/reset-password', [
        'email' => $user->email,
        'token' => $resetToken,
        'password' => 'NewPassword999',
        'password_confirmation' => 'NewPassword999',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

