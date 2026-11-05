<?php

use App\Models\Category;
use App\Models\Holiday;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->adminToken = $this->admin->createToken('admin')->plainTextToken;

    $this->agent = User::factory()->agent()->create();
    $this->agentToken = $this->agent->createToken('agent')->plainTextToken;

    $this->customer = User::factory()->customer()->create();
    $this->customerToken = $this->customer->createToken('customer')->plainTextToken;

    $this->category = Category::factory()->create(['is_active' => true]);
});

test('admin can create a holiday with 201 response', function () {
    $response = $this->withToken($this->adminToken)
        ->postJson('/api/v1/admin/holidays', [
            'date' => '2026-12-16',
            'name' => 'Victory Day',
        ]);

    $response->assertStatus(201);
    $data = $response->json('data') ?? $response->json();

    expect($data['date'])->toBe('2026-12-16')
        ->and($data['name'])->toBe('Victory Day')
        ->and($data)->toHaveKey('id');
});

test('admin can list holidays by year sorted by date', function () {
    // 2026 holidays out of order
    Holiday::create(['date' => '2026-12-16', 'name' => 'Victory Day']);
    Holiday::create(['date' => '2026-02-21', 'name' => 'Martyrs Day']);
    // 2025 holiday
    Holiday::create(['date' => '2025-12-25', 'name' => 'Christmas 2025']);

    $response = $this->withToken($this->adminToken)
        ->getJson('/api/v1/admin/holidays?year=2026');

    $response->assertOk();
    $data = $response->json('data') ?? $response->json();

    expect(count($data))->toBe(2)
        ->and($data[0]['date'])->toBe('2026-02-21')
        ->and($data[1]['date'])->toBe('2026-12-16');
});

test('duplicate holiday date returns 422 on date', function () {
    Holiday::create(['date' => '2026-12-16', 'name' => 'Victory Day']);

    $response = $this->withToken($this->adminToken)
        ->postJson('/api/v1/admin/holidays', [
            'date' => '2026-12-16',
            'name' => 'Another Holiday Same Day',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['date']);
});

test('admin can delete a holiday with 204 response', function () {
    $holiday = Holiday::create(['date' => '2026-05-01', 'name' => 'May Day']);

    $response = $this->withToken($this->adminToken)
        ->deleteJson("/api/v1/admin/holidays/{$holiday->id}");

    $response->assertStatus(204);

    expect(Holiday::find($holiday->id))->toBeNull();
});

test('agent can list holidays but cannot create or delete them', function () {
    $holiday = Holiday::create(['date' => '2026-05-01', 'name' => 'May Day']);

    // Agent list
    $this->withToken($this->agentToken)->getJson('/api/v1/admin/holidays')->assertOk();
    // Agent create
    $this->withToken($this->agentToken)->postJson('/api/v1/admin/holidays', ['date' => '2026-06-01', 'name' => 'Day'])->assertForbidden();
    // Agent delete
    $this->withToken($this->agentToken)->deleteJson("/api/v1/admin/holidays/{$holiday->id}")->assertForbidden();
});

test('customers and inactive agents are forbidden from holidays endpoints', function () {
    $holiday = Holiday::create(['date' => '2026-05-01', 'name' => 'May Day']);

    // Inactive Agent
    $inactiveAgent = User::factory()->agent()->create(['is_active' => false]);
    $inactiveAgentToken = $inactiveAgent->createToken('inactive-agent')->plainTextToken;
    $this->withToken($inactiveAgentToken)->getJson('/api/v1/admin/holidays')->assertForbidden();

    // Customer
    $this->app['auth']->forgetGuards();
    $this->withToken($this->customerToken)->getJson('/api/v1/admin/holidays')->assertForbidden();
    $this->withToken($this->customerToken)->postJson('/api/v1/admin/holidays', ['date' => '2026-06-01', 'name' => 'Day'])->assertForbidden();
    $this->withToken($this->customerToken)->deleteJson("/api/v1/admin/holidays/{$holiday->id}")->assertForbidden();
});

test('adding a holiday skips it for new tickets while existing tickets keep deadline', function () {
    $wednesday = CarbonImmutable::parse('2026-11-11 17:30:00', 'Asia/Dhaka');
    $this->travelTo($wednesday);

    // Create ticket on Wednesday 17:30 before holiday is added (due Thursday 10:30)
    $beforeTicket = $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Ticket created before holiday added',
            'description' => 'Should be due Thursday 10:30.',
            'category_id' => $this->category->id,
            'customer_id' => $this->customer->id,
            'priority' => 'critical',
        ]);
    $beforeNumber = $beforeTicket->json('ticket_number');
    $beforeDue = $beforeTicket->json('sla.due_at');
    expect(CarbonImmutable::parse($beforeDue)->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))
        ->toBe('2026-11-12 10:30');

    // Admin adds Thursday 2026-11-12 as a holiday
    $this->app['auth']->forgetGuards();
    $this->withToken($this->adminToken)
        ->postJson('/api/v1/admin/holidays', [
            'date' => '2026-11-12',
            'name' => 'Special Holiday',
        ])
        ->assertStatus(201);

    // Existing ticket keeps deadline
    $this->app['auth']->forgetGuards();
    $beforeRefreshed = $this->withToken($this->agentToken)->getJson("/api/v1/tickets/{$beforeNumber}");
    expect($beforeRefreshed->json('sla.due_at'))->toBe($beforeDue);

    // New ticket created Wednesday 17:30 skips Thursday holiday, Friday & Saturday closed -> due Sunday 10:30
    $afterTicket = $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Ticket created after holiday added',
            'description' => 'Should skip Thursday and be due Sunday 10:30.',
            'category_id' => $this->category->id,
            'customer_id' => $this->customer->id,
            'priority' => 'critical',
        ]);
    $afterDue = $afterTicket->json('sla.due_at');
    expect(CarbonImmutable::parse($afterDue)->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))
        ->toBe('2026-11-15 10:30');
});

