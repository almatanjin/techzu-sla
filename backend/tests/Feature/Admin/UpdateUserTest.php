<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->token = $this->admin->createToken('admin-token')->plainTextToken;
});

it('promotes a customer to agent', function () {
    $customer = User::factory()->customer()->create([
        'name' => 'Original Customer',
        'email' => 'customer@example.test',
    ]);

    $response = $this->withToken($this->token)
        ->patchJson("/api/v1/admin/users/{$customer->id}", [
            'role' => 'agent',
        ]);

    $response->assertOk()
        ->assertJson([
            'id' => $customer->id,
            'name' => 'Original Customer',
            'email' => 'customer@example.test',
            'role' => 'agent',
            'is_active' => true,
        ]);

    expect($customer->refresh()->role)->toBe(UserRole::Agent);
});

it('partially updates user while keeping untouched fields intact', function () {
    $user = User::factory()->customer()->create([
        'name' => 'Old Name',
        'email' => 'keepme@example.test',
        'is_active' => true,
    ]);

    $response = $this->withToken($this->token)
        ->patchJson("/api/v1/admin/users/{$user->id}", [
            'name' => 'Brand New Name',
        ]);

    $response->assertOk()
        ->assertJson([
            'name' => 'Brand New Name',
            'email' => 'keepme@example.test',
            'role' => 'customer',
            'is_active' => true,
        ]);

    $fresh = $user->refresh();
    expect($fresh->name)->toBe('Brand New Name')
        ->and($fresh->email)->toBe('keepme@example.test')
        ->and($fresh->role)->toBe(UserRole::Customer)
        ->and($fresh->is_active)->toBeTrue();
});

it('revokes all tokens when user is deactivated', function () {
    $agent = User::factory()->agent()->create();
    $agentToken = $agent->createToken('agent-device')->plainTextToken;

    // Agent can access /auth/me initially
    $this->withToken($agentToken)
        ->getJson('/api/v1/auth/me')
        ->assertOk();

    $this->app['auth']->forgetGuards();

    // Admin deactivates the agent
    $response = $this->withToken($this->token)
        ->patchJson("/api/v1/admin/users/{$agent->id}", [
            'is_active' => false,
        ]);

    $response->assertOk()
        ->assertJson(['is_active' => false]);

    $this->app['auth']->forgetGuards();

    // Agent token is now revoked and returns 401
    $this->withToken($agentToken)
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

it('allows reactivated user to log in again', function () {
    $user = User::factory()->customer()->inactive()->create([
        'email' => 'reactivate@example.test',
        'password' => 'Pass12345',
    ]);

    // Initial login fails due to inactive
    $this->postJson('/api/v1/auth/login', [
        'email' => 'reactivate@example.test',
        'password' => 'Pass12345',
    ])->assertStatus(422);

    // Admin reactivates the user
    $this->withToken($this->token)
        ->patchJson("/api/v1/admin/users/{$user->id}", [
            'is_active' => true,
        ])->assertOk();

    // Login now succeeds
    $this->postJson('/api/v1/auth/login', [
        'email' => 'reactivate@example.test',
        'password' => 'Pass12345',
    ])->assertOk();
});

it('prevents administrator from changing their own role', function () {
    $response = $this->withToken($this->token)
        ->patchJson("/api/v1/admin/users/{$this->admin->id}", [
            'role' => 'agent',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['role']);

    expect($this->admin->refresh()->role)->toBe(UserRole::Admin);
});

it('prevents administrator from deactivating their own account', function () {
    $response = $this->withToken($this->token)
        ->patchJson("/api/v1/admin/users/{$this->admin->id}", [
            'is_active' => false,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['is_active']);

    expect($this->admin->refresh()->is_active)->toBeTrue();
});

it('returns 405 Method Not Allowed on DELETE requests to admin users', function () {
    $target = User::factory()->customer()->create();

    $response = $this->withToken($this->token)
        ->deleteJson("/api/v1/admin/users/{$target->id}");

    $response->assertStatus(405);
    expect(User::find($target->id))->not->toBeNull();
});

it('allows admin to update agent settings', function () {
    $rafi = User::factory()->agent()->create([
        'name' => 'Rafi',
    ]);
    $billing = Category::factory()->create(['name' => 'Billing']);
    $technical = Category::factory()->create(['name' => 'Technical']);

    $response = $this->withToken($this->token)
        ->patchJson("/api/v1/admin/users/{$rafi->id}", [
            'is_available' => false,
            'max_open_tickets' => 5,
            'category_ids' => [$billing->id, $technical->id],
        ]);

    $response->assertOk()
        ->assertJson([
            'id' => $rafi->id,
            'agent_settings' => [
                'is_available' => false,
                'max_open_tickets' => 5,
                'category_ids' => [$billing->id, $technical->id],
            ],
        ]);

    $getResponse = $this->withToken($this->token)
        ->getJson("/api/v1/admin/users/{$rafi->id}");

    $getResponse->assertOk()
        ->assertJson([
            'id' => $rafi->id,
            'agent_settings' => [
                'is_available' => false,
                'max_open_tickets' => 5,
                'category_ids' => [$billing->id, $technical->id],
            ],
        ]);
});

it('rejects agent settings fields sent for a customer with 422 on that field', function (string $field, mixed $value) {
    $customer = User::factory()->customer()->create();

    $response = $this->withToken($this->token)
        ->patchJson("/api/v1/admin/users/{$customer->id}", [
            $field => $value,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([$field]);
})->with([
    'is_available' => ['is_available', false],
    'max_open_tickets' => ['max_open_tickets', 5],
    'category_ids' => ['category_ids', [1]],
]);

it('allows setting agent settings while promoting customer to agent', function () {
    $customer = User::factory()->customer()->create();

    $response = $this->withToken($this->token)
        ->patchJson("/api/v1/admin/users/{$customer->id}", [
            'role' => 'agent',
            'max_open_tickets' => 5,
        ]);

    $response->assertOk()
        ->assertJson([
            'id' => $customer->id,
            'role' => 'agent',
            'agent_settings' => [
                'max_open_tickets' => 5,
            ],
        ]);
});

it('rejects out of range max_open_tickets with 422', function (int $value) {
    $agent = User::factory()->agent()->create();

    $response = $this->withToken($this->token)
        ->patchJson("/api/v1/admin/users/{$agent->id}", [
            'max_open_tickets' => $value,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['max_open_tickets']);
})->with([0, 1001]);

it('rejects unknown category id with 422', function () {
    $agent = User::factory()->agent()->create();

    $response = $this->withToken($this->token)
        ->patchJson("/api/v1/admin/users/{$agent->id}", [
            'category_ids' => [999999],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['category_ids.0']);
});

it('clears max_open_tickets when sent as null', function () {
    $agent = User::factory()->agent()->create([
        'max_open_tickets' => 5,
    ]);

    $response = $this->withToken($this->token)
        ->patchJson("/api/v1/admin/users/{$agent->id}", [
            'max_open_tickets' => null,
        ]);

    $response->assertOk()
        ->assertJson([
            'id' => $agent->id,
            'agent_settings' => [
                'max_open_tickets' => null,
            ],
        ]);

    expect($agent->refresh()->max_open_tickets)->toBeNull();
});

it('forbids an agent from sending settings for themselves with 403', function () {
    $agent = User::factory()->agent()->create();
    $token = $agent->createToken('agent-token')->plainTextToken;

    $response = $this->withToken($token)
        ->patchJson("/api/v1/admin/users/{$agent->id}", [
            'is_available' => false,
        ]);

    $response->assertForbidden();
});

it('returns null for customer agent_settings in user show response', function () {
    $customer = User::factory()->customer()->create();

    $response = $this->withToken($this->token)
        ->getJson("/api/v1/admin/users/{$customer->id}");

    $response->assertOk()
        ->assertJson([
            'id' => $customer->id,
            'agent_settings' => null,
        ]);
});






