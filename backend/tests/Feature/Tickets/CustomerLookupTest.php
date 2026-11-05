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

it('allows an agent to fetch a customer by id and returns id, name and email', function () {
    $ana = User::factory()->customer()->create(['name' => 'Ana Rahman', 'email' => 'ana@example.test']);

    $response = $this->withToken($this->agentToken)->getJson("/api/v1/customers/{$ana->id}");

    $response->assertOk();
    $data = $response->json('data') ?? $response->json();
    expect($data)->toMatchArray([
        'id' => $ana->id,
        'name' => 'Ana Rahman',
        'email' => 'ana@example.test',
    ]);
});

it('returns a deactivated customer when looked up by id', function () {
    $deactivated = User::factory()->customer()->inactive()->create([
        'name' => 'Anton Inactive',
        'email' => 'anton@example.test',
    ]);

    $response = $this->withToken($this->agentToken)->getJson("/api/v1/customers/{$deactivated->id}");

    $response->assertOk();
    $data = $response->json('data') ?? $response->json();
    expect($data)->toMatchArray([
        'id' => $deactivated->id,
        'name' => 'Anton Inactive',
        'email' => 'anton@example.test',
    ]);
});

it('returns 404 when looking up an agent id or unknown id', function () {
    $andy = User::factory()->agent()->create(['name' => 'Andy Agent']);

    $this->withToken($this->agentToken)
        ->getJson("/api/v1/customers/{$andy->id}")
        ->assertNotFound();

    $this->withToken($this->agentToken)
        ->getJson('/api/v1/customers/999999')
        ->assertNotFound();
});

it('forbids a customer from looking up another customer by id with 403', function () {
    $ben = User::factory()->customer()->create(['name' => 'Ben']);

    $this->withToken($this->customerToken)
        ->getJson("/api/v1/customers/{$ben->id}")
        ->assertForbidden();
});

