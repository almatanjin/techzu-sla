<?php

use App\Enums\TicketActivityType;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->token = $this->admin->createToken('admin-token')->plainTextToken;
    $this->category = Category::factory()->create();
});

it('allows admin to manually assign an open unassigned ticket', function () {
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => null,
        'status' => TicketStatus::Open,
        'lock_version' => 0,
    ]);

    $response = $this->withToken($this->token)
        ->putJson("/api/v1/tickets/{$ticket->ticket_number}/assignment", [
            'agent_id' => $rafi->id,
            'lock_version' => 0,
        ]);

    $response->assertOk()
        ->assertJson([
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'status' => 'open',
            'lock_version' => 1,
            'assigned_agent' => [
                'id' => $rafi->id,
                'name' => 'Rafi',
            ],
        ]);

    $getResponse = $this->withToken($this->token)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}");

    $getResponse->assertOk()
        ->assertJson([
            'id' => $ticket->id,
            'assigned_agent' => [
                'id' => $rafi->id,
                'name' => 'Rafi',
            ],
            'lock_version' => 1,
        ]);

    $activitiesResponse = $this->withToken($this->token)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities");

    $activitiesResponse->assertOk();
    $activities = $activitiesResponse->json();

    $lastActivity = end($activities);
    expect($lastActivity['type'])->toBe('assigned')
        ->and($lastActivity['actor']['id'])->toBe($this->admin->id)
        ->and($lastActivity['old_values'])->toBeNull()
        ->and($lastActivity['new_values'])->toBe([
            'agent' => [
                'id' => $rafi->id,
                'name' => 'Rafi',
            ],
        ]);
});

it('allows admin to reassign ticket to an agent at their maximum and logs old and new assignee', function () {
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $mina = User::factory()->agent()->create([
        'name' => 'Mina',
        'max_open_tickets' => 3,
    ]);

    // Mina has 3 active tickets (at her maximum)
    Ticket::factory()->count(3)->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $mina->id,
        'status' => TicketStatus::Open,
    ]);

    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $rafi->id,
        'status' => TicketStatus::Open,
        'lock_version' => 1,
    ]);

    $response = $this->withToken($this->token)
        ->putJson("/api/v1/tickets/{$ticket->ticket_number}/assignment", [
            'agent_id' => $mina->id,
            'lock_version' => 1,
        ]);

    $response->assertOk()
        ->assertJson([
            'id' => $ticket->id,
            'assigned_agent' => [
                'id' => $mina->id,
                'name' => 'Mina',
            ],
            'lock_version' => 2,
        ]);

    $activities = $this->withToken($this->token)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities")
        ->json();

    $lastActivity = end($activities);
    expect($lastActivity['type'])->toBe('assigned')
        ->and($lastActivity['old_values'])->toBe([
            'agent' => [
                'id' => $rafi->id,
                'name' => 'Rafi',
            ],
        ])
        ->and($lastActivity['new_values'])->toBe([
            'agent' => [
                'id' => $mina->id,
                'name' => 'Mina',
            ],
        ]);
});

it('allows admin to unassign ticket with agent_id null and logs unassigned', function () {
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $rafi->id,
        'status' => TicketStatus::Open,
        'lock_version' => 1,
    ]);

    $response = $this->withToken($this->token)
        ->putJson("/api/v1/tickets/{$ticket->ticket_number}/assignment", [
            'agent_id' => null,
            'lock_version' => 1,
        ]);

    $response->assertOk()
        ->assertJson([
            'id' => $ticket->id,
            'assigned_agent' => null,
            'lock_version' => 2,
        ]);

    $activities = $this->withToken($this->token)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities")
        ->json();

    $lastActivity = end($activities);
    expect($lastActivity['type'])->toBe('unassigned')
        ->and($lastActivity['old_values'])->toBe([
            'agent' => [
                'id' => $rafi->id,
                'name' => 'Rafi',
            ],
        ])
        ->and($lastActivity['new_values'])->toBeNull();
});

it('changes nothing and logs nothing when assigning current assignee', function () {
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $rafi->id,
        'status' => TicketStatus::Open,
        'lock_version' => 1,
    ]);

    $initialActivitiesCount = count($this->withToken($this->token)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities")
        ->json());

    $response = $this->withToken($this->token)
        ->putJson("/api/v1/tickets/{$ticket->ticket_number}/assignment", [
            'agent_id' => $rafi->id,
            'lock_version' => 1,
        ]);

    $response->assertOk()
        ->assertJson([
            'id' => $ticket->id,
            'lock_version' => 1,
            'assigned_agent' => [
                'id' => $rafi->id,
            ],
        ]);

    $finalActivitiesCount = count($this->withToken($this->token)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities")
        ->json());

    expect($finalActivitiesCount)->toBe($initialActivitiesCount);
});

it('rejects invalid targets with 422 on agent_id', function (Closure $targetFactory) {
    $target = $targetFactory();
    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => null,
        'status' => TicketStatus::Open,
        'lock_version' => 0,
    ]);

    $response = $this->withToken($this->token)
        ->putJson("/api/v1/tickets/{$ticket->ticket_number}/assignment", [
            'agent_id' => $target->id,
            'lock_version' => 0,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['agent_id']);
})->with([
    'customer' => fn () => User::factory()->customer()->create(),
    'deactivated_agent' => fn () => User::factory()->agent()->inactive()->create(),
    'unavailable_agent' => fn () => User::factory()->agent()->unavailable()->create(),
    'admin' => fn () => User::factory()->admin()->create(),
]);

it('rejects stale lock_version with 409 and changes nothing', function () {
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => null,
        'status' => TicketStatus::Open,
        'lock_version' => 2,
    ]);

    $response = $this->withToken($this->token)
        ->putJson("/api/v1/tickets/{$ticket->ticket_number}/assignment", [
            'agent_id' => $rafi->id,
            'lock_version' => 1,
        ]);

    $response->assertStatus(409)
        ->assertJson([
            'message' => 'This ticket was changed by someone else. The latest version is now shown.',
            'ticket' => [
                'id' => $ticket->id,
                'lock_version' => 2,
                'assigned_agent' => null,
            ],
        ]);

    expect($ticket->refresh()->assigned_agent_id)->toBeNull();
});

it('forbids agent from assigning ticket with 403', function () {
    $agent = User::factory()->agent()->create();
    $token = $agent->createToken('agent-token')->plainTextToken;

    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'status' => TicketStatus::Open,
        'lock_version' => 0,
    ]);

    $response = $this->withToken($token)
        ->putJson("/api/v1/tickets/{$ticket->ticket_number}/assignment", [
            'agent_id' => $agent->id,
            'lock_version' => 0,
        ]);

    $response->assertForbidden();
});

it('forbids assignment changes on resolved and closed tickets with 403', function (TicketStatus $status) {
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $mina = User::factory()->agent()->create(['name' => 'Mina']);

    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $rafi->id,
        'status' => $status,
        'lock_version' => 1,
    ]);

    $response = $this->withToken($this->token)
        ->putJson("/api/v1/tickets/{$ticket->ticket_number}/assignment", [
            'agent_id' => $mina->id,
            'lock_version' => 1,
        ]);

    $response->assertForbidden();
    expect($ticket->refresh()->assigned_agent_id)->toBe($rafi->id);
})->with([
    'resolved' => TicketStatus::Resolved,
    'closed' => TicketStatus::Closed,
]);







