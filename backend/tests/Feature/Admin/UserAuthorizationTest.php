<?php

use App\Models\User;

dataset('adminUserEndpoints', [
    'index' => [fn () => ['GET', '/api/v1/admin/users', []]],
    'show' => [fn (User $target) => ['GET', "/api/v1/admin/users/{$target->id}", []]],
    'store' => [fn () => ['POST', '/api/v1/admin/users', [
        'name' => 'New User',
        'email' => 'newuser@example.test',
        'role' => 'agent',
        'password' => 'SecurePass123',
    ]]],
    'update' => [fn (User $target) => ['PATCH', "/api/v1/admin/users/{$target->id}", [
        'name' => 'Updated Name',
    ]]],
]);

it('rejects unauthenticated requests to admin user endpoints with 401', function (Closure $routeDefinition) {
    $target = User::factory()->customer()->create();
    [$method, $url, $payload] = $routeDefinition($target);

    $this->json($method, $url, $payload)
        ->assertUnauthorized();
})->with('adminUserEndpoints');

it('forbids customer and agent access to admin user endpoints with 403', function (string $role, Closure $routeDefinition) {
    $actor = $role === 'agent'
        ? User::factory()->agent()->create()
        : User::factory()->customer()->create();

    $target = User::factory()->customer()->create();
    [$method, $url, $payload] = $routeDefinition($target);

    $token = $actor->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->json($method, $url, $payload)
        ->assertForbidden();
})->with([
    'customer' => 'customer',
    'agent' => 'agent',
])->with('adminUserEndpoints');

it('forbids inactive admin access to admin user endpoints with 403', function (Closure $routeDefinition) {
    $inactiveAdmin = User::factory()->admin()->inactive()->create();
    $target = User::factory()->customer()->create();
    [$method, $url, $payload] = $routeDefinition($target);

    $token = $inactiveAdmin->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->json($method, $url, $payload)
        ->assertForbidden();
})->with('adminUserEndpoints');

it('allows active admin access to admin user endpoints', function (Closure $routeDefinition) {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->customer()->create();
    [$method, $url, $payload] = $routeDefinition($target);

    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->json($method, $url, $payload);
    expect($response->status())->toBeIn([200, 201]);
})->with('adminUserEndpoints');
