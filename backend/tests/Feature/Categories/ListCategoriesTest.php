<?php

use App\Models\Category;
use App\Models\User;

beforeEach(function () {
    $this->customer = User::factory()->customer()->create();
    $this->agent = User::factory()->agent()->create();
    $this->admin = User::factory()->admin()->create();

    $this->customerToken = $this->customer->createToken('customer')->plainTextToken;
    $this->agentToken = $this->agent->createToken('agent')->plainTextToken;
    $this->adminToken = $this->admin->createToken('admin')->plainTextToken;
});

it('lists active categories sorted by name for any signed-in user', function () {
    Category::factory()->create(['name' => 'Support', 'is_active' => true]);
    Category::factory()->create(['name' => 'Billing', 'is_active' => true]);
    Category::factory()->inactive()->create(['name' => 'Archived']);

    $response = $this->withToken($this->customerToken)
        ->getJson('/api/v1/categories');

    $response->assertOk();
    $names = collect($response->json())->pluck('name')->all();

    expect($names)->toBe(['Billing', 'Support']);
});

it('ignores include_inactive parameter for customer and agent', function () {
    Category::factory()->create(['name' => 'Active Category', 'is_active' => true]);
    Category::factory()->inactive()->create(['name' => 'Inactive Category']);

    $responseCustomer = $this->withToken($this->customerToken)
        ->getJson('/api/v1/categories?include_inactive=1');

    $responseCustomer->assertOk();
    $customerNames = collect($responseCustomer->json())->pluck('name')->all();
    expect($customerNames)->toBe(['Active Category']);

    $responseAgent = $this->withToken($this->agentToken)
        ->getJson('/api/v1/categories?include_inactive=1');

    $responseAgent->assertOk();
    $agentNames = collect($responseAgent->json())->pluck('name')->all();
    expect($agentNames)->toBe(['Active Category']);
});

it('honors include_inactive parameter for admins', function () {
    Category::factory()->create(['name' => 'Billing', 'is_active' => true]);
    Category::factory()->inactive()->create(['name' => 'Legacy']);

    $response = $this->withToken($this->adminToken)
        ->getJson('/api/v1/categories?include_inactive=1');

    $response->assertOk();
    $categories = collect($response->json());
    expect($categories->pluck('name')->all())->toBe(['Billing', 'Legacy']);

    $legacy = $categories->firstWhere('name', 'Legacy');
    expect($legacy['is_active'])->toBeFalse();
});

it('rejects unauthenticated requests to categories list with 401', function () {
    $this->getJson('/api/v1/categories')
        ->assertUnauthorized();
});

