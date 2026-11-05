<?php

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;

it('automatically assigns a new ticket to an available agent handling the category', function () {
    $category = Category::factory()->create(['name' => 'Billing', 'is_active' => true]);
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $rafi->categories()->attach($category);

    $customer = User::factory()->customer()->create();
    $token = $customer->createToken('customer-token')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Billing question',
            'description' => 'I have a question about my invoice.',
            'category_id' => $category->id,
        ]);

    $response->assertCreated();
    $ticketNumber = $response->json('ticket_number');

    $getResponse = $this->withToken($token)
        ->getJson("/api/v1/tickets/{$ticketNumber}");

    $getResponse->assertOk()
        ->assertJson([
            'assigned_agent' => [
                'id' => $rafi->id,
                'name' => 'Rafi',
            ],
        ]);

    $activitiesResponse = $this->withToken($token)
        ->getJson("/api/v1/tickets/{$ticketNumber}/activities");

    $activities = $activitiesResponse->json();
    $lastActivity = end($activities);

    expect($lastActivity['type'])->toBe('assigned')
        ->and($lastActivity['actor'])->toBeNull()
        ->and($lastActivity['old_values'])->toBeNull()
        ->and($lastActivity['new_values'])->toBe([
            'agent' => [
                'id' => $rafi->id,
                'name' => 'Rafi',
            ],
        ]);
});

it('leaves new ticket unassigned when every agent is unavailable', function () {
    $category = Category::factory()->create(['name' => 'Billing', 'is_active' => true]);
    $agent = User::factory()->agent()->unavailable()->create(['name' => 'Unavailable Agent']);
    $agent->categories()->attach($category);

    $customer = User::factory()->customer()->create();
    $token = $customer->createToken('customer-token')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Help needed',
            'description' => 'Nobody is around.',
            'category_id' => $category->id,
        ]);

    $response->assertCreated();
    $ticketNumber = $response->json('ticket_number');

    $getResponse = $this->withToken($token)
        ->getJson("/api/v1/tickets/{$ticketNumber}");

    $getResponse->assertOk()
        ->assertJson([
            'assigned_agent' => null,
        ]);

    $activities = $this->withToken($token)
        ->getJson("/api/v1/tickets/{$ticketNumber}/activities")
        ->json();

    expect($activities)->toHaveCount(1)
        ->and($activities[0]['type'])->toBe('created');
});

it('falls back to the less loaded agent when nobody handles the category', function () {
    $otherCategory = Category::factory()->create(['name' => 'General', 'is_active' => true]);
    $accountCategory = Category::factory()->create(['name' => 'Account', 'is_active' => true]);

    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $mina = User::factory()->agent()->create(['name' => 'Mina']);

    // Rafi has 2 active tickets, Mina has 1 active ticket
    Ticket::factory()->count(2)->create([
        'category_id' => $otherCategory->id,
        'assigned_agent_id' => $rafi->id,
        'status' => TicketStatus::Open,
    ]);

    Ticket::factory()->create([
        'category_id' => $otherCategory->id,
        'assigned_agent_id' => $mina->id,
        'status' => TicketStatus::Open,
    ]);

    $customer = User::factory()->customer()->create();
    $token = $customer->createToken('customer-token')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Account problem',
            'description' => 'Cannot sign in.',
            'category_id' => $accountCategory->id,
        ]);

    $response->assertCreated();
    $ticketNumber = $response->json('ticket_number');

    $getResponse = $this->withToken($token)
        ->getJson("/api/v1/tickets/{$ticketNumber}");

    $getResponse->assertOk()
        ->assertJson([
            'assigned_agent' => [
                'id' => $mina->id,
                'name' => 'Mina',
            ],
        ]);
});

it('advances round robin between agents with equal workloads across travelTo times', function () {
    $category = Category::factory()->create(['name' => 'Billing', 'is_active' => true]);

    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $rafi->categories()->attach($category);

    $mina = User::factory()->agent()->create(['name' => 'Mina']);
    $mina->categories()->attach($category);

    $customer = User::factory()->customer()->create();
    $token = $customer->createToken('customer-token')->plainTextToken;

    // First ticket at 10:00
    $this->travelTo('2026-11-05 10:00:00');

    $res1 = $this->withToken($token)->postJson('/api/v1/tickets', [
        'subject' => 'Ticket 1',
        'description' => 'First issue',
        'category_id' => $category->id,
    ]);
    $res1->assertCreated();

    $ticket1 = Ticket::where('ticket_number', $res1->json('ticket_number'))->first();
    expect($ticket1->assigned_agent_id)->toBe($rafi->id);

    // Keep active workloads equal (0 and 0) by closing ticket 1
    $ticket1->update(['status' => TicketStatus::Closed]);

    // Second ticket at 11:00
    $this->travelTo('2026-11-05 11:00:00');

    $res2 = $this->withToken($token)->postJson('/api/v1/tickets', [
        'subject' => 'Ticket 2',
        'description' => 'Second issue',
        'category_id' => $category->id,
    ]);
    $res2->assertCreated();

    $ticket2 = Ticket::where('ticket_number', $res2->json('ticket_number'))->first();
    expect($ticket2->assigned_agent_id)->toBe($mina->id);
});

it('skips agent who has reached their maximum open tickets', function () {
    $category = Category::factory()->create(['name' => 'Billing', 'is_active' => true]);

    $rafi = User::factory()->agent()->create([
        'name' => 'Rafi',
        'max_open_tickets' => 2,
    ]);
    $rafi->categories()->attach($category);

    $mina = User::factory()->agent()->create([
        'name' => 'Mina',
        'max_open_tickets' => null,
    ]);
    $mina->categories()->attach($category);

    // Rafi has 2 active tickets (at max 2)
    Ticket::factory()->count(2)->create([
        'category_id' => $category->id,
        'assigned_agent_id' => $rafi->id,
        'status' => TicketStatus::Open,
    ]);

    // Mina has 5 active tickets (no max)
    Ticket::factory()->count(5)->create([
        'category_id' => $category->id,
        'assigned_agent_id' => $mina->id,
        'status' => TicketStatus::Open,
    ]);

    $customer = User::factory()->customer()->create();
    $token = $customer->createToken('customer-token')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Billing issue',
            'description' => 'Need help.',
            'category_id' => $category->id,
        ]);

    $response->assertCreated();
    $ticket = Ticket::where('ticket_number', $response->json('ticket_number'))->first();

    expect($ticket->assigned_agent_id)->toBe($mina->id);
});

it('keeps assignee and adds no assigned activity when claimed before queued auto-assign job runs', function () {
    \Illuminate\Support\Facades\Queue::fake([App\Jobs\AutoAssignTicket::class]);

    $category = Category::factory()->create(['name' => 'Billing', 'is_active' => true]);

    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $rafi->categories()->attach($category);

    $mina = User::factory()->agent()->create(['name' => 'Mina']);
    $minaToken = $mina->createToken('mina-token')->plainTextToken;

    $customer = User::factory()->customer()->create();
    $token = $customer->createToken('customer-token')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Quick Billing Issue',
            'description' => 'Fast claim test',
            'category_id' => $category->id,
        ]);

    $response->assertCreated();
    $ticketNumber = $response->json('ticket_number');
    $ticket = Ticket::where('ticket_number', $ticketNumber)->first();

    \Illuminate\Support\Facades\Queue::assertPushed(App\Jobs\AutoAssignTicket::class);

    // Forget cached user on guard between requests in the same test
    auth('sanctum')->forgetUser();

    // Mina claims before job executes
    $this->withToken($minaToken)
        ->postJson("/api/v1/tickets/{$ticketNumber}/claim")
        ->assertOk();

    // Now execute the queued job manually
    $job = new App\Jobs\AutoAssignTicket($ticket->id);
    $job->handle(app(App\Services\Assignment\TicketAssignmentService::class));

    // Ticket still belongs to Mina
    expect($ticket->refresh()->assigned_agent_id)->toBe($mina->id);

    // History has only created and claimed, no assigned entry
    $activities = $this->withToken($token)
        ->getJson("/api/v1/tickets/{$ticketNumber}/activities")
        ->json();

    $types = collect($activities)->pluck('type')->all();
    expect($types)->toBe(['created', 'claimed']);
});





