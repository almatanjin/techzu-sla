<?php

use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->token = $this->admin->createToken('admin-token')->plainTextToken;
});

it('creates an agent with lowercased email who can log in with the given password', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/admin/users', [
            'name' => 'Rafi',
            'email' => 'Rafi@Example.test',
            'role' => 'agent',
            'password' => 'SecretPass123',
            'is_active' => true,
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'name' => 'Rafi',
            'email' => 'rafi@example.test',
            'role' => 'agent',
            'is_active' => true,
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'rafi@example.test',
        'role' => 'agent',
        'is_active' => true,
    ]);

    // Created agent can log in with the password
    $this->postJson('/api/v1/auth/login', [
        'email' => 'rafi@example.test',
        'password' => 'SecretPass123',
    ])->assertOk();
});

it('rejects duplicate email in different case with 422', function () {
    User::factory()->create([
        'email' => 'existing@example.test',
    ]);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/admin/users', [
            'name' => 'Existing Copy',
            'email' => 'EXISTING@EXAMPLE.TEST',
            'role' => 'agent',
            'password' => 'SecretPass123',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

