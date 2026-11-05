<?php

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;

beforeEach(function () {
    $this->category = Category::factory()->create();
});

it('allows assignee to release their in-progress ticket', function () {
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $token = $rafi->createToken('agent-token')->plainTextToken;

    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $rafi->id,
        'status' => TicketStatus::InProgress,
        'lock_version' => 2,
    ]);

    $response = $this->withToken($token)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/release");

    $response->assertOk()
        ->assertJson([
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'status' => 'in_progress',
            'assigned_agent' => null,
            'lock_version' => 3,
        ]);

    $activities = $this->withToken($token)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities")
        ->json();

    $lastActivity = end($activities);
    expect($lastActivity['type'])->toBe('unassigned')
        ->and($lastActivity['actor']['id'])->toBe($rafi->id)
        ->and($lastActivity['old_values'])->toBe([
            'agent' => [
                'id' => $rafi->id,
                'name' => 'Rafi',
            ],
        ])
        ->and($lastActivity['new_values'])->toBeNull();
});

it('forbids non-assignee, admin non-assignee, and unassigned ticket release with 403', function () {
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $mina = User::factory()->agent()->create(['name' => 'Mina']);
    $minaToken = $mina->createToken('agent-token')->plainTextToken;

    $admin = User::factory()->admin()->create(['name' => 'Admin']);
    $adminToken = $admin->createToken('admin-token')->plainTextToken;

    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $rafi->id,
        'status' => TicketStatus::InProgress,
        'lock_version' => 1,
    ]);

    $unassignedTicket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => null,
        'status' => TicketStatus::Open,
        'lock_version' => 0,
    ]);

    // Mina releasing Rafi's ticket
    $this->withToken($minaToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/release")
        ->assertForbidden();

    // Admin who is not the assignee
    $this->withToken($adminToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/release")
        ->assertForbidden();

    // Release of unassigned ticket
    $this->withToken($minaToken)
        ->postJson("/api/v1/tickets/{$unassignedTicket->ticket_number}/release")
        ->assertForbidden();

    expect($ticket->refresh()->assigned_agent_id)->toBe($rafi->id)
        ->and($unassignedTicket->refresh()->assigned_agent_id)->toBeNull();
});

