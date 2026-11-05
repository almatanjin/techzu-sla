<?php

use App\Models\User;

beforeEach(function () {
    $this->agent = User::factory()->agent()->create();
    $this->agentToken = $this->agent->createToken('agent')->plainTextToken;

    $this->customer = User::factory()->customer()->create();
    $this->customerToken = $this->customer->createToken('customer')->plainTextToken;
});

it('allows staff to search active customers and excludes staff and inactive customers', function () {
    $ana = User::factory()->customer()->create(['name' => 'Ana Rahman', 'email' => 'ana@example.test']);
    $anika = User::factory()->customer()->create(['name' => 'Anika Roy', 'email' => 'anika@example.test']);
    $bob = User::factory()->customer()->create(['name' => 'Bob Smith', 'email' => 'bob@example.test']);

    $andy = User::factory()->agent()->create(['name' => 'Andy Agent', 'email' => 'andy@example.test']);
    $adam = User::factory()->admin()->create(['name' => 'Adam Admin', 'email' => 'adam@example.test']);
    $anton = User::factory()->customer()->inactive()->create(['name' => 'Anton Inactive', 'email' => 'anton@example.test']);

    $response = $this->withToken($this->agentToken)
        ->getJson('/api/v1/customers?search=an');

    $response->assertOk();
    $data = $response->json();

    $ids = collect($data)->pluck('id')->all();
    expect($ids)->toContain($ana->id, $anika->id)
        ->and($ids)->not->toContain($bob->id)
        ->and($ids)->not->toContain($andy->id)
        ->and($ids)->not->toContain($adam->id)
        ->and($ids)->not->toContain($anton->id);

    expect($data[0])->toHaveKeys(['id', 'name', 'email'])
        ->not->toHaveKeys(['role', 'is_active', 'created_at']);
});

it('caps customer search results at 20', function () {
    for ($i = 0; $i < 25; $i++) {
        User::factory()->customer()->create([
            'name' => "Searchable Customer {$i}",
            'email' => "searchable{$i}@example.test",
        ]);
    }

    $response = $this->withToken($this->agentToken)
        ->getJson('/api/v1/customers?search=Searchable');

    $response->assertOk();
    expect(count($response->json()))->toBe(20);
});

it('forbids customers from accessing customer lookup with 403', function () {
    $this->withToken($this->customerToken)
        ->getJson('/api/v1/customers')
        ->assertForbidden();
});

