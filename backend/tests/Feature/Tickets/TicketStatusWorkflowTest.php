<?php

use App\Enums\TicketActivityType;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->customer()->create();
    $this->ownerToken = $this->owner->createToken('owner')->plainTextToken;

    $this->otherCustomer = User::factory()->customer()->create();
    $this->otherToken = $this->otherCustomer->createToken('other')->plainTextToken;

    $this->agent = User::factory()->agent()->create();
    $this->agentToken = $this->agent->createToken('agent')->plainTextToken;

    $this->category = Category::factory()->create();
});

dataset('workflow_transitions', function () {
    return [
        // Staff role
        ['agent', 'open', 'open', 422],
        ['agent', 'open', 'in_progress', 200],
        ['agent', 'open', 'waiting_for_customer', 200],
        ['agent', 'open', 'resolved', 200],
        ['agent', 'open', 'closed', 200],
        ['agent', 'in_progress', 'open', 422],
        ['agent', 'in_progress', 'in_progress', 422],
        ['agent', 'in_progress', 'waiting_for_customer', 200],
        ['agent', 'in_progress', 'resolved', 200],
        ['agent', 'in_progress', 'closed', 200],
        ['agent', 'waiting_for_customer', 'open', 422],
        ['agent', 'waiting_for_customer', 'in_progress', 200],
        ['agent', 'waiting_for_customer', 'waiting_for_customer', 422],
        ['agent', 'waiting_for_customer', 'resolved', 200],
        ['agent', 'waiting_for_customer', 'closed', 200],
        ['agent', 'resolved', 'open', 403],
        ['agent', 'resolved', 'in_progress', 200],
        ['agent', 'resolved', 'waiting_for_customer', 422],
        ['agent', 'resolved', 'resolved', 422],
        ['agent', 'resolved', 'closed', 200],
        ['agent', 'closed', 'open', 422],
        ['agent', 'closed', 'in_progress', 422],
        ['agent', 'closed', 'waiting_for_customer', 422],
        ['agent', 'closed', 'resolved', 422],
        ['agent', 'closed', 'closed', 422],

        // Owner customer
        ['owner', 'open', 'open', 422],
        ['owner', 'open', 'in_progress', 403],
        ['owner', 'open', 'waiting_for_customer', 403],
        ['owner', 'open', 'resolved', 403],
        ['owner', 'open', 'closed', 200],
        ['owner', 'in_progress', 'open', 422],
        ['owner', 'in_progress', 'in_progress', 422],
        ['owner', 'in_progress', 'waiting_for_customer', 403],
        ['owner', 'in_progress', 'resolved', 403],
        ['owner', 'in_progress', 'closed', 200],
        ['owner', 'waiting_for_customer', 'open', 422],
        ['owner', 'waiting_for_customer', 'in_progress', 403],
        ['owner', 'waiting_for_customer', 'waiting_for_customer', 422],
        ['owner', 'waiting_for_customer', 'resolved', 403],
        ['owner', 'waiting_for_customer', 'closed', 200],
        ['owner', 'resolved', 'open', 200],
        ['owner', 'resolved', 'in_progress', 403],
        ['owner', 'resolved', 'waiting_for_customer', 422],
        ['owner', 'resolved', 'resolved', 422],
        ['owner', 'resolved', 'closed', 200],
        ['owner', 'closed', 'open', 422],
        ['owner', 'closed', 'in_progress', 422],
        ['owner', 'closed', 'waiting_for_customer', 422],
        ['owner', 'closed', 'resolved', 422],
        ['owner', 'closed', 'closed', 422],

        // Other customer (all transitions: 403 if valid state machine transition, 422 if invalid)
        ['other', 'open', 'in_progress', 403],
        ['other', 'open', 'closed', 403],
        ['other', 'open', 'open', 422],
        ['other', 'resolved', 'open', 403],
        ['other', 'closed', 'open', 422],
    ];
});

it('enforces workflow table transition matrix across roles', function (string $role, string $from, string $to, int $expectedStatus) {
    $token = match ($role) {
        'agent' => $this->agentToken,
        'owner' => $this->ownerToken,
        'other' => $this->otherToken,
    };

    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::from($from),
        'customer_id' => $this->owner->id,
        'lock_version' => 1,
    ]);

    $response = $this->withToken($token)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/status", [
            'status' => $to,
            'lock_version' => 1,
        ]);

    $response->assertStatus($expectedStatus);
})->with('workflow_transitions');

it('handles resolved_at and closed_at timestamps and clears resolved_at on reopen', function () {
    $ticket = Ticket::factory()->inProgress()->create([
        'customer_id' => $this->owner->id,
        'lock_version' => 1,
    ]);

    // Agent resolves ticket
    $this->withToken($this->agentToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/status", [
            'status' => 'resolved',
            'lock_version' => 1,
        ])
        ->assertOk();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Resolved)
        ->and($ticket->resolved_at)->not->toBeNull()
        ->and($ticket->closed_at)->toBeNull()
        ->and($ticket->lock_version)->toBe(2);

    $this->app['auth']->forgetGuards();

    // Customer reopens ticket
    $this->withToken($this->ownerToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/status", [
            'status' => 'open',
            'lock_version' => 2,
        ])
        ->assertOk();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->resolved_at)->toBeNull()
        ->and($ticket->lock_version)->toBe(3);

    // Owner closes ticket
    $this->withToken($this->ownerToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/status", [
            'status' => 'closed',
            'lock_version' => 3,
        ])
        ->assertOk();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Closed)
        ->and($ticket->closed_at)->not->toBeNull()
        ->and($ticket->lock_version)->toBe(4);
});

it('records status activity entry on status transition', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->owner->id,
        'lock_version' => 0,
    ]);

    $this->withToken($this->agentToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/status", [
            'status' => 'in_progress',
            'lock_version' => 0,
        ])
        ->assertOk();

    $activity = TicketActivity::where('ticket_id', $ticket->id)->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->type)->toBe(TicketActivityType::StatusChanged)
        ->and($activity->actor_id)->toBe($this->agent->id)
        ->and($activity->old_values)->toBe(['status' => 'open'])
        ->and($activity->new_values)->toBe(['status' => 'in_progress']);
});

it('rejects stale lock_version with 409 conflict and creates no activity', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->owner->id,
        'lock_version' => 5,
    ]);

    $response = $this->withToken($this->agentToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/status", [
            'status' => 'in_progress',
            'lock_version' => 4, // stale
        ]);

    $response->assertStatus(409)
        ->assertJson([
            'message' => 'This ticket was changed by someone else. The latest version is now shown.',
            'ticket' => [
                'ticket_number' => $ticket->ticket_number,
                'status' => 'open',
                'lock_version' => 5,
            ],
        ]);

    expect($ticket->refresh()->status)->toBe(TicketStatus::Open)
        ->and($ticket->lock_version)->toBe(5);

    expect(TicketActivity::where('ticket_id', $ticket->id)->count())->toBe(0);
});
