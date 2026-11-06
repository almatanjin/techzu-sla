<?php

use App\Enums\TicketPriority;
use App\Models\Category;
use App\Models\Ticket;
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

test('admin can get all default sla policies', function () {
    $response = $this->withToken($this->adminToken)
        ->getJson('/api/v1/admin/sla-policies');

    $response->assertOk();
    $data = $response->json('data') ?? $response->json();

    expect(count($data))->toBe(4);
    $byPriority = collect($data)->keyBy('priority');

    expect($byPriority['low']['resolution_minutes'])->toBe(2880)
        ->and($byPriority['low']['warning_threshold_percent'])->toBe(75)
        ->and($byPriority['medium']['resolution_minutes'])->toBe(1440)
        ->and($byPriority['high']['resolution_minutes'])->toBe(480)
        ->and($byPriority['critical']['resolution_minutes'])->toBe(120);
});

test('admin can patch high sla policy', function () {
    $response = $this->withToken($this->adminToken)
        ->patchJson('/api/v1/admin/sla-policies/high', [
            'resolution_minutes' => 600,
            'warning_threshold_percent' => 80,
        ]);

    $response->assertOk();
    $data = $response->json('data') ?? $response->json();

    expect($data['priority'])->toBe('high')
        ->and($data['resolution_minutes'])->toBe(600)
        ->and($data['warning_threshold_percent'])->toBe(80);

    // Verify persisted
    $getResponse = $this->withToken($this->adminToken)
        ->getJson('/api/v1/admin/sla-policies');
    $high = collect($getResponse->json('data') ?? $getResponse->json())->firstWhere('priority', 'high');
    expect($high['resolution_minutes'])->toBe(600)
        ->and($high['warning_threshold_percent'])->toBe(80);
});

test('out-of-range sla policy values answer 422', function (array $payload, string $errorField) {
    $response = $this->withToken($this->adminToken)
        ->patchJson('/api/v1/admin/sla-policies/high', $payload);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([$errorField]);
})->with([
    [['resolution_minutes' => 5], 'resolution_minutes'],
    [['resolution_minutes' => 43201], 'resolution_minutes'],
    [['warning_threshold_percent' => 0], 'warning_threshold_percent'],
    [['warning_threshold_percent' => 100], 'warning_threshold_percent'],
]);

test('agents are forbidden from reading or changing sla policies', function () {
    $this->withToken($this->agentToken)
        ->getJson('/api/v1/admin/sla-policies')
        ->assertForbidden();

    $this->withToken($this->agentToken)
        ->patchJson('/api/v1/admin/sla-policies/high', ['resolution_minutes' => 600])
        ->assertForbidden();
});

test('customers and inactive agents are forbidden from sla policies endpoints', function () {
    // Inactive Agent
    $inactiveAgent = User::factory()->agent()->create(['is_active' => false]);
    $inactiveAgentToken = $inactiveAgent->createToken('inactive-agent')->plainTextToken;
    $this->withToken($inactiveAgentToken)
        ->getJson('/api/v1/admin/sla-policies')
        ->assertForbidden();

    // Customer
    $this->app['auth']->forgetGuards();
    $this->withToken($this->customerToken)
        ->getJson('/api/v1/admin/sla-policies')
        ->assertForbidden();

    $this->withToken($this->customerToken)
        ->patchJson('/api/v1/admin/sla-policies/high', ['resolution_minutes' => 600])
        ->assertForbidden();
});

test('shortening critical policy leaves existing ticket due time intact but affects new ticket', function () {
    $now = CarbonImmutable::parse('2026-11-08 09:00:00', 'Asia/Dhaka');
    $this->travelTo($now);

    // Create Critical ticket before policy change (120 minutes -> due 11:00)
    $existing = $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Existing critical ticket',
            'description' => 'Created under 120-minute policy.',
            'category_id' => $this->category->id,
            'customer_id' => $this->customer->id,
            'priority' => 'critical',
        ]);
    $existingNumber = $existing->json('ticket_number');
    $existingDue = $existing->json('sla.due_at');

    // Admin updates Critical policy to 60 minutes
    $this->app['auth']->forgetGuards();
    $this->withToken($this->adminToken)
        ->patchJson('/api/v1/admin/sla-policies/critical', [
            'resolution_minutes' => 60,
        ])
        ->assertOk();

    // Existing ticket keeps due time
    $this->app['auth']->forgetGuards();
    $existingRefreshed = $this->withToken($this->agentToken)->getJson("/api/v1/tickets/{$existingNumber}");
    expect($existingRefreshed->json('sla.due_at'))->toBe($existingDue);

    // New ticket gets 60-minute due time (Sunday 09:00 + 60m = Sunday 10:00)
    $newTicket = $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'New critical ticket after policy change',
            'description' => 'Should be due in 60 minutes.',
            'category_id' => $this->category->id,
            'customer_id' => $this->customer->id,
            'priority' => 'critical',
        ]);
    $newDue = $newTicket->json('sla.due_at');
    expect(CarbonImmutable::parse($newDue)->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))
        ->toBe('2026-11-08 10:00');
});

