<?php

use App\Models\User;

it('registers a customer successfully', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ana Silva',
        'email' => 'ana@example.test',
        'password' => 'SecurePass123',
        'password_confirmation' => 'SecurePass123',
    ]);

    $response->assertStatus(201)
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
        ->and($response->json('user.role'))->toBe('customer')
        ->and($response->json('user.is_active'))->toBeTrue()
        ->and($response->json('user.email'))->toBe('ana@example.test');

    $this->assertDatabaseHas('users', [
        'email' => 'ana@example.test',
        'role' => 'customer',
        'is_active' => true,
    ]);
});

it('ignores the role field in the registration request', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Sneaky User',
        'email' => 'sneaky@example.test',
        'role' => 'admin',
        'password' => 'SecurePass123',
        'password_confirmation' => 'SecurePass123',
    ]);

    $response->assertStatus(201);
    expect($response->json('user.role'))->toBe('customer');

    $this->assertDatabaseHas('users', [
        'email' => 'sneaky@example.test',
        'role' => 'customer',
    ]);
});

it('rejects duplicate email in different case', function () {
    User::factory()->create([
        'email' => 'ana@example.test',
    ]);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ana Another',
        'email' => 'ANA@example.test',
        'password' => 'SecurePass123',
        'password_confirmation' => 'SecurePass123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('rejects weak password without numbers or letters', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Weak Pass',
        'email' => 'weak@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

