<?php

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;

beforeEach(function () {
    $this->category = Category::factory()->create();
});

it('allows an agent to claim an open unassigned ticket', function () {
    $agent = User::factory()->agent()->create(['name' => 'Rafi']);
    $token = $agent->createToken('agent-token')->plainTextToken;

    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => null,
        'status' => TicketStatus::Open,
        'lock_version' => 1,
    ]);

    $response = $this->withToken($token)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/claim");

    $response->assertOk()
        ->assertJson([
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'status' => 'open',
            'lock_version' => 2,
            'assigned_agent' => [
                'id' => $agent->id,
                'name' => 'Rafi',
            ],
        ]);

    $activities = $this->withToken($token)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities")
        ->json();

    $lastActivity = end($activities);
    expect($lastActivity['type'])->toBe('claimed')
        ->and($lastActivity['actor']['id'])->toBe($agent->id)
        ->and($lastActivity['old_values'])->toBeNull()
        ->and($lastActivity['new_values'])->toBe([
            'agent' => [
                'id' => $agent->id,
                'name' => 'Rafi',
            ],
        ]);
});

it('rejects claim on ticket held by Mina with 409 and already taken message', function () {
    $mina = User::factory()->agent()->create(['name' => 'Mina']);
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $token = $rafi->createToken('agent-token')->plainTextToken;

    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $mina->id,
        'status' => TicketStatus::Open,
        'lock_version' => 2,
    ]);

    $response = $this->withToken($token)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/claim");

    $response->assertStatus(409)
        ->assertJson([
            'message' => 'This ticket was already taken by Mina.',
            'ticket' => [
                'id' => $ticket->id,
                'assigned_agent' => [
                    'id' => $mina->id,
                    'name' => 'Mina',
                ],
                'lock_version' => 2,
            ],
        ]);
});

it('allows an admin to claim an open unassigned ticket', function () {
    $admin = User::factory()->admin()->create(['name' => 'Admin Boss']);
    $token = $admin->createToken('admin-token')->plainTextToken;

    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => null,
        'status' => TicketStatus::Open,
        'lock_version' => 0,
    ]);

    $response = $this->withToken($token)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/claim");

    $response->assertOk()
        ->assertJson([
            'id' => $ticket->id,
            'assigned_agent' => [
                'id' => $admin->id,
                'name' => 'Admin Boss',
            ],
            'lock_version' => 1,
        ]);
});

it('forbids customer and resolved ticket claim with 403', function () {
    $customer = User::factory()->customer()->create();
    $customerToken = $customer->createToken('customer-token')->plainTextToken;

    $agent = User::factory()->agent()->create();
    $agentToken = $agent->createToken('agent-token')->plainTextToken;

    $openTicket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'customer_id' => $customer->id,
        'assigned_agent_id' => null,
        'status' => TicketStatus::Open,
    ]);

    $resolvedTicket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => null,
        'status' => TicketStatus::Resolved,
    ]);

    // Customer claiming
    $this->withToken($customerToken)
        ->postJson("/api/v1/tickets/{$openTicket->ticket_number}/claim")
        ->assertForbidden();

    // Agent claiming resolved ticket
    $this->withToken($agentToken)
        ->postJson("/api/v1/tickets/{$resolvedTicket->ticket_number}/claim")
        ->assertForbidden();
});

