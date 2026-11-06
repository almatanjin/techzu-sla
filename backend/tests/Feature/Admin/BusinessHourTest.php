<?php

use App\Models\Category;
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

test('admin can get business hours', function () {
    $response = $this->withToken($this->adminToken)
        ->getJson('/api/v1/admin/business-hours');

    $response->assertOk();
    $data = $response->json('data') ?? $response->json();

    expect($data['timezone'])->toBe('Asia/Dhaka')
        ->and(count($data['days']))->toBe(7);

    // Day 0 (Sunday) to 4 (Thursday) working 09:00 to 18:00
    for ($i = 0; $i <= 4; $i++) {
        expect($data['days'][$i]['day_of_week'])->toBe($i)
            ->and($data['days'][$i]['is_working_day'])->toBeTrue()
            ->and($data['days'][$i]['opens_at'])->toStartWith('09:00')
            ->and($data['days'][$i]['closes_at'])->toStartWith('18:00');
    }

    // Friday and Saturday closed
    expect($data['days'][5]['is_working_day'])->toBeFalse()
        ->and($data['days'][6]['is_working_day'])->toBeFalse();
});

test('admin can update business hours with Thursday closed', function () {
    $current = $this->withToken($this->adminToken)
        ->getJson('/api/v1/admin/business-hours')
        ->json();
    $days = $current['data']['days'] ?? $current['days'];

    // Close Thursday (day 4)
    $days[4]['is_working_day'] = false;
    $days[4]['opens_at'] = null;
    $days[4]['closes_at'] = null;

    $updateResponse = $this->withToken($this->adminToken)
        ->putJson('/api/v1/admin/business-hours', [
            'days' => $days,
        ]);

    $updateResponse->assertOk();

    // Verify GET reflects the change
    $getResponse = $this->withToken($this->adminToken)
        ->getJson('/api/v1/admin/business-hours');
    $getDays = $getResponse->json('data.days') ?? $getResponse->json('days');
    expect($getDays[4]['is_working_day'])->toBeFalse();
});

test('schedule change affects new tickets and leaves existing tickets intact', function () {
    // Ticket created on Wednesday 17:30 before change
    $wednesday = CarbonImmutable::parse('2026-11-11 17:30:00', 'Asia/Dhaka');
    $this->travelTo($wednesday);

    $ticketBefore = $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Ticket created before schedule change',
            'description' => 'Created Wednesday 17:30.',
            'category_id' => $this->category->id,
            'customer_id' => $this->customer->id,
            'priority' => 'critical',
        ]);
    $ticketBeforeNumber = $ticketBefore->json('ticket_number');
    $ticketBeforeDue = $ticketBefore->json('sla.due_at');

    // Admin closes Thursday (day 4)
    $this->app['auth']->forgetGuards();
    $current = $this->withToken($this->adminToken)
        ->getJson('/api/v1/admin/business-hours')
        ->json();
    $days = $current['data']['days'] ?? $current['days'];
    $days[4]['is_working_day'] = false;
    $days[4]['opens_at'] = null;
    $days[4]['closes_at'] = null;

    $this->app['auth']->forgetGuards();
    $this->withToken($this->adminToken)
        ->putJson('/api/v1/admin/business-hours', ['days' => $days])
        ->assertOk();

    // Existing ticket keeps due time
    $this->app['auth']->forgetGuards();
    $existing = $this->withToken($this->agentToken)->getJson("/api/v1/tickets/{$ticketBeforeNumber}");
    expect($existing->json('sla.due_at'))->toBe($ticketBeforeDue);

    // New critical ticket created at Wednesday 17:30 after change
    // 2 business hours: 30m Wed (17:30-18:00), Thu closed, Fri closed, Sat closed, 90m Sun (09:00-10:30) -> Sunday 10:30
    $ticketAfter = $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Ticket created after schedule change',
            'description' => 'Created Wednesday 17:30 after Thursday closed.',
            'category_id' => $this->category->id,
            'customer_id' => $this->customer->id,
            'priority' => 'critical',
        ]);
    $ticketAfterDue = $ticketAfter->json('sla.due_at');
    expect(CarbonImmutable::parse($ticketAfterDue)->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))
        ->toBe('2026-11-15 10:30');
});

test('invalid business hours payloads return 422', function () {
    // 1. No working day
    $noWorkingDays = array_map(fn ($i) => [
        'day_of_week' => $i,
        'is_working_day' => false,
        'opens_at' => null,
        'closes_at' => null,
    ], range(0, 6));

    $this->withToken($this->adminToken)
        ->putJson('/api/v1/admin/business-hours', ['days' => $noWorkingDays])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['days']);

    // 2. Sunday closing before opening (e.g. opens 18:00, closes 09:00)
    $invalidSunday = array_map(fn ($i) => [
        'day_of_week' => $i,
        'is_working_day' => $i < 5,
        'opens_at' => $i === 0 ? '18:00' : '09:00',
        'closes_at' => $i === 0 ? '09:00' : '18:00',
    ], range(0, 6));

    $this->withToken($this->adminToken)
        ->putJson('/api/v1/admin/business-hours', ['days' => $invalidSunday])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['days.0.closes_at']);

    // 3. Malformed time
    $malformedTime = array_map(fn ($i) => [
        'day_of_week' => $i,
        'is_working_day' => $i < 5,
        'opens_at' => $i === 0 ? '25:00' : '09:00',
        'closes_at' => '18:00',
    ], range(0, 6));

    $this->withToken($this->adminToken)
        ->putJson('/api/v1/admin/business-hours', ['days' => $malformedTime])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['days.0.opens_at']);
});

test('agents are forbidden from reading or updating business hours', function () {
    $this->withToken($this->agentToken)
        ->getJson('/api/v1/admin/business-hours')
        ->assertForbidden();

    $this->withToken($this->agentToken)
        ->putJson('/api/v1/admin/business-hours', ['days' => []])
        ->assertForbidden();
});

test('customers and inactive agents cannot access business hours endpoints', function () {
    // Inactive agent
    $inactiveAgent = User::factory()->agent()->create(['is_active' => false]);
    $inactiveAgentToken = $inactiveAgent->createToken('inactive-agent')->plainTextToken;
    $this->withToken($inactiveAgentToken)
        ->getJson('/api/v1/admin/business-hours')
        ->assertForbidden();

    // Customer
    $this->app['auth']->forgetGuards();
    $this->withToken($this->customerToken)
        ->getJson('/api/v1/admin/business-hours')
        ->assertForbidden();

    $this->withToken($this->customerToken)
        ->putJson('/api/v1/admin/business-hours', ['days' => []])
        ->assertForbidden();
});
