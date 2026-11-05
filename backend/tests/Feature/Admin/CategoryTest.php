<?php

use App\Models\Category;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->adminToken = $this->admin->createToken('admin')->plainTextToken;

    $this->agent = User::factory()->agent()->create();
    $this->agentToken = $this->agent->createToken('agent')->plainTextToken;

    $this->customer = User::factory()->customer()->create();
    $this->customerToken = $this->customer->createToken('customer')->plainTextToken;
});

it('allows admin to create a category', function () {
    $response = $this->withToken($this->adminToken)
        ->postJson('/api/v1/admin/categories', [
            'name' => 'Shipping',
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'name' => 'Shipping',
            'is_active' => true,
        ]);

    $this->assertDatabaseHas('categories', [
        'name' => 'Shipping',
        'is_active' => true,
    ]);
});

it('allows admin to rename a category and toggle active status', function () {
    $category = Category::factory()->create([
        'name' => 'Hardware',
        'is_active' => true,
    ]);

    $response = $this->withToken($this->adminToken)
        ->patchJson("/api/v1/admin/categories/{$category->id}", [
            'name' => 'Hardware & Devices',
            'is_active' => false,
        ]);

    $response->assertOk()
        ->assertJson([
            'id' => $category->id,
            'name' => 'Hardware & Devices',
            'is_active' => false,
        ]);

    expect($category->refresh()->name)->toBe('Hardware & Devices')
        ->and($category->is_active)->toBeFalse();
});

it('rejects duplicate name in different case with 422 on create and rename', function () {
    Category::factory()->create(['name' => 'Billing']);
    $other = Category::factory()->create(['name' => 'Technical']);

    // Duplicate on create
    $this->withToken($this->adminToken)
        ->postJson('/api/v1/admin/categories', [
            'name' => 'billing',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    // Duplicate on rename
    $this->withToken($this->adminToken)
        ->patchJson("/api/v1/admin/categories/{$other->id}", [
            'name' => 'BILLING',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

it('allows renaming a category with the same name without duplicate error', function () {
    $category = Category::factory()->create(['name' => 'Billing']);

    $this->withToken($this->adminToken)
        ->patchJson("/api/v1/admin/categories/{$category->id}", [
            'name' => 'Billing',
            'is_active' => false,
        ])
        ->assertOk();
});

it('forbids agent and customer from creating or updating categories with 403', function () {
    $category = Category::factory()->create(['name' => 'Billing']);

    // Agent create
    $this->withToken($this->agentToken)
        ->postJson('/api/v1/admin/categories', ['name' => 'Agent Category'])
        ->assertForbidden();

    // Customer create
    $this->withToken($this->customerToken)
        ->postJson('/api/v1/admin/categories', ['name' => 'Customer Category'])
        ->assertForbidden();

    // Agent update
    $this->withToken($this->agentToken)
        ->patchJson("/api/v1/admin/categories/{$category->id}", ['name' => 'New Name'])
        ->assertForbidden();

    // Customer update
    $this->withToken($this->customerToken)
        ->patchJson("/api/v1/admin/categories/{$category->id}", ['name' => 'New Name'])
        ->assertForbidden();
});

it('returns 405 Method Not Allowed on DELETE requests to admin categories', function () {
    $category = Category::factory()->create(['name' => 'Doomed']);

    $response = $this->withToken($this->adminToken)
        ->deleteJson("/api/v1/admin/categories/{$category->id}");

    $response->assertStatus(405);
    expect(Category::find($category->id))->not->toBeNull();
});

