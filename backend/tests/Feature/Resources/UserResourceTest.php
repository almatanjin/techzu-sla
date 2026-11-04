<?php

use App\Enums\UserRole;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

it('serializes expected user attributes and omits sensitive fields', function () {
    $user = User::factory()->agent()->create([
        'name' => 'Agent Smith',
        'email' => 'smith@example.test',
        'password' => 'secret-hashed',
        'remember_token' => 'token123',
    ]);

    $resource = new UserResource($user);
    $data = $resource->toArray(Request::create('/'));

    expect($data)->toHaveKeys(['id', 'name', 'email', 'role', 'is_active', 'created_at'])
        ->and($data['name'])->toBe('Agent Smith')
        ->and($data['email'])->toBe('smith@example.test')
        ->and($data['role'])->toBe('agent')
        ->and($data['is_active'])->toBeTrue()
        ->and($data)->not->toHaveKey('password')
        ->and($data)->not->toHaveKey('remember_token');

    $json = $resource->response()->getData(true);
    $payload = $json['data'] ?? $json;
    expect($payload)->not->toHaveKey('password')
        ->and($payload)->not->toHaveKey('remember_token');
});

