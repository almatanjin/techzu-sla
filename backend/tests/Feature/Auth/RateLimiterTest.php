<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('login');
    RateLimiter::clear('register');
    RateLimiter::clear('password-reset');
});

it('throttles login attempts per email and IP address', function () {
    $user = User::factory()->customer()->create([
        'email' => 'ana@example.test',
        'password' => 'CorrectPass123',
    ]);

    User::factory()->customer()->create([
        'email' => 'ben@example.test',
        'password' => 'CorrectPass123',
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'ana@example.test',
            'password' => 'WrongPass',
        ])->assertStatus(422);
    }

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ana@example.test',
        'password' => 'WrongPass',
    ]);

    $response->assertStatus(429)
        ->assertHeader('Retry-After');

    // A different email from the same IP is not throttled
    $this->postJson('/api/v1/auth/login', [
        'email' => 'ben@example.test',
        'password' => 'WrongPass',
    ])->assertStatus(422);
});

it('throttles registrations per IP address', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/register', [
            'name' => "User {$i}",
            'email' => "user{$i}@example.test",
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
        ])->assertStatus(201);
    }

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'User 6',
        'email' => 'user6@example.test',
        'password' => 'SecurePass123',
        'password_confirmation' => 'SecurePass123',
    ]);

    $response->assertStatus(429)
        ->assertHeader('Retry-After');
});

