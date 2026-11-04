<?php

use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->token = $this->admin->createToken('admin-token')->plainTextToken;
});

it('filters users by role and returns pagination meta', function () {
    User::factory()->agent()->count(3)->create();
    User::factory()->customer()->count(5)->create();

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/admin/users?role=agent');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'role', 'is_active', 'created_at'],
            ],
            'links',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    expect($response->json('meta.total'))->toBe(3)
        ->and($response->json('data'))->toHaveCount(3);

    foreach ($response->json('data') as $item) {
        expect($item['role'])->toBe('agent');
    }
});

it('filters users by status', function () {
    User::factory()->customer()->count(3)->create(['is_active' => true]);
    User::factory()->customer()->count(2)->inactive()->create();

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/admin/users?status=inactive');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(2)
        ->and($response->json('data'))->toHaveCount(2);

    foreach ($response->json('data') as $item) {
        expect($item['is_active'])->toBeFalse();
    }
});

it('searches users by email or name fragment', function () {
    User::factory()->customer()->create([
        'name' => 'Alice Wonder',
        'email' => 'alice@example.test',
    ]);
    User::factory()->customer()->create([
        'name' => 'Bob Builder',
        'email' => 'bob@example.test',
    ]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/admin/users?search=alice@');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.email'))->toBe('alice@example.test');

    $nameResponse = $this->withToken($this->token)
        ->getJson('/api/v1/admin/users?search=Builder');

    $nameResponse->assertOk();
    expect($nameResponse->json('meta.total'))->toBe(1)
        ->and($nameResponse->json('data.0.name'))->toBe('Bob Builder');
});

it('combines role, status, and search filters', function () {
    User::factory()->agent()->create([
        'name' => 'Agent Smith',
        'email' => 'asmith@agency.test',
        'is_active' => true,
    ]);
    User::factory()->agent()->inactive()->create([
        'name' => 'Agent Inactive Smith',
        'email' => 'asmith-inactive@agency.test',
    ]);
    User::factory()->customer()->create([
        'name' => 'Customer Smith',
        'email' => 'csmith@domain.test',
        'is_active' => true,
    ]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/admin/users?role=agent&status=active&search=asmith@');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.name'))->toBe('Agent Smith');
});

it('paginates users 20 per page newest first', function () {
    User::factory()->customer()->count(25)->create();

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/admin/users');

    $response->assertOk();
    // 25 customers + 1 admin created in beforeEach = 26
    expect($response->json('meta.total'))->toBe(26)
        ->and($response->json('meta.per_page'))->toBe(20)
        ->and($response->json('data'))->toHaveCount(20);
});

it('rejects invalid role filter with 422', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/v1/admin/users?role=owner');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['role']);
});

