<?php

use App\Enums\UserRole;
use App\Models\User;

it('creates an agent user with the agent role', function () {
    $user = User::factory()->agent()->create();

    expect($user->role)->toBe(UserRole::Agent)
        ->and($user->isAgent())->toBeTrue()
        ->and($user->isAdmin())->toBeFalse()
        ->and($user->isCustomer())->toBeFalse();
});

it('stores mixed-case emails in lowercase', function () {
    $user = User::factory()->create([
        'email' => 'MixedCase.User@Example.Test',
    ]);

    expect($user->email)->toBe('mixedcase.user@example.test');

    $user->refresh();
    expect($user->email)->toBe('mixedcase.user@example.test');
});

it('supports admin, customer, and inactive factory states', function () {
    $admin = User::factory()->admin()->create();
    expect($admin->role)->toBe(UserRole::Admin)
        ->and($admin->isAdmin())->toBeTrue()
        ->and($admin->is_active)->toBeTrue();

    $customer = User::factory()->customer()->create();
    expect($customer->role)->toBe(UserRole::Customer)
        ->and($customer->isCustomer())->toBeTrue();

    $inactive = User::factory()->inactive()->create();
    expect($inactive->is_active)->toBeFalse();
});

