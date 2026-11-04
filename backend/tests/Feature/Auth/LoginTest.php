<?php

use App\Models\User;

it('authenticates active user with correct credentials and returns token expiring in 7 days', function () {
    $user = User::factory()->customer()->create([
        'email' => 'login.user@example.test',
        'password' => 'CorrectPassword123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'LOGIN.USER@EXAMPLE.TEST',
        'password' => 'CorrectPassword123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'token_type',
            'expires_at',
            'user' => [
                'id',
                'name',
                'email',
                'role',
                'is_active',
                'created_at',
            ],
        ]);

    expect($response->json('token_type'))->toBe('Bearer')
        ->and($response->json('user.id'))->toBe($user->id)
        ->and($response->json('user.email'))->toBe('login.user@example.test')
        ->and($response->json('user.role'))->toBe('customer')
        ->and($response->json('user.is_active'))->toBeTrue();

    $expiresAt = \Illuminate\Support\Carbon::parse($response->json('expires_at'));
    $expectedExpiry = now()->addDays(7);
    expect($expiresAt->diffInMinutes($expectedExpiry))->toBeLessThanOrEqual(1);
});

it('rejects wrong password with 422 on email without a token', function () {
    User::factory()->create([
        'email' => 'someone@example.test',
        'password' => 'CorrectPassword123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'someone@example.test',
        'password' => 'WrongPassword999',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email'])
        ->assertJsonMissing(['token']);
});

it('rejects deactivated account with a specific deactivation message and no token', function () {
    User::factory()->inactive()->create([
        'email' => 'deactivated@example.test',
        'password' => 'CorrectPassword123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'deactivated@example.test',
        'password' => 'CorrectPassword123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email'])
        ->assertJsonFragment([
            'email' => ['This account has been deactivated.'],
        ])
        ->assertJsonMissing(['token']);
});

it('rejects a token used after 7 days plus one minute', function () {
    $user = User::factory()->customer()->create([
        'password' => 'CorrectPassword123',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'CorrectPassword123',
    ]);

    $token = $loginResponse->json('token');

    $this->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertOk();

    $this->travel(7)->days();
    $this->travel(1)->minute();
    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});
