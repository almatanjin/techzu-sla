<?php

use App\Models\User;

it('rejects unauthenticated requests to auth me with 401 and JSON message', function () {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertUnauthorized()
        ->assertJsonStructure(['message']);
});

it('returns the current user without password or sensitive fields', function () {
    $user = User::factory()->agent()->create([
        'name' => 'Agent Scully',
        'email' => 'scully@example.test',
        'password' => 'SecurePass123',
    ]);

    $token = $user->createToken('api')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJson([
            'id' => $user->id,
            'name' => 'Agent Scully',
            'email' => 'scully@example.test',
            'role' => 'agent',
            'is_active' => true,
        ])
        ->assertJsonMissing(['password', 'remember_token']);
});

it('revokes only the token used on logout leaving other tokens active', function () {
    $user = User::factory()->customer()->create();

    $tokenA = $user->createToken('device-a')->plainTextToken;
    $tokenB = $user->createToken('device-b')->plainTextToken;

    // Both tokens work initially
    $this->withToken($tokenA)
        ->getJson('/api/v1/auth/me')
        ->assertOk();

    $this->app['auth']->forgetGuards();

    $this->withToken($tokenB)
        ->getJson('/api/v1/auth/me')
        ->assertOk();

    $this->app['auth']->forgetGuards();

    // Logout with token A
    $response = $this->withToken($tokenA)
        ->postJson('/api/v1/auth/logout');

    $response->assertNoContent();

    $this->app['auth']->forgetGuards();

    // Token A is revoked
    $this->withToken($tokenA)
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();

    $this->app['auth']->forgetGuards();

    // Token B still works
    $this->withToken($tokenB)
        ->getJson('/api/v1/auth/me')
        ->assertOk();
});

