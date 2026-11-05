<?php

use App\Enums\TicketActivityType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;

beforeEach(function () {
    $this->customer = User::factory()->customer()->create();
    $this->customerToken = $this->customer->createToken('customer')->plainTextToken;

    $this->agent = User::factory()->agent()->create();
    $this->agentToken = $this->agent->createToken('agent')->plainTextToken;

    $this->admin = User::factory()->admin()->create();
    $this->adminToken = $this->admin->createToken('admin')->plainTextToken;

    $this->category = Category::factory()->create(['name' => 'Billing']);
});

it('allows customer to edit subject and description while ticket is open', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
        'subject' => 'Old subject',
        'description' => 'Old description',
        'lock_version' => 0,
    ]);

    $response = $this->withToken($this->customerToken)
        ->patchJson("/api/v1/tickets/{$ticket->ticket_number}", [
            'subject' => 'New subject',
            'lock_version' => 0,
        ]);

    $response->assertOk()
        ->assertJson([
            'subject' => 'New subject',
            'lock_version' => 1,
        ]);

    expect($ticket->refresh()->subject)->toBe('New subject')
        ->and($ticket->lock_version)->toBe(1);

    $activity = TicketActivity::where('ticket_id', $ticket->id)->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->type)->toBe(TicketActivityType::SubjectChanged)
        ->and($activity->actor_id)->toBe($this->customer->id)
        ->and($activity->old_values)->toBe(['subject' => 'Old subject'])
        ->and($activity->new_values)->toBe(['subject' => 'New subject']);
});

it('forbids customer from editing ticket once work has started (in_progress)', function () {
    $ticket = Ticket::factory()->inProgress()->create([
        'customer_id' => $this->customer->id,
        'description' => 'Original description',
        'lock_version' => 0,
    ]);

    $this->withToken($this->customerToken)
        ->patchJson("/api/v1/tickets/{$ticket->ticket_number}", [
            'description' => 'Attempted change',
            'lock_version' => 0,
        ])
        ->assertForbidden();

    expect($ticket->refresh()->description)->toBe('Original description');
});

it('forbids customer from changing ticket priority', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
        'priority' => TicketPriority::Low,
        'lock_version' => 0,
    ]);

    $this->withToken($this->customerToken)
        ->patchJson("/api/v1/tickets/{$ticket->ticket_number}", [
            'priority' => 'critical',
            'lock_version' => 0,
        ])
        ->assertForbidden();

    expect($ticket->refresh()->priority)->toBe(TicketPriority::Low);
});

it('allows staff to reclassify ticket and records two separate activities', function () {
    $techCategory = Category::factory()->create(['name' => 'Technical']);
    $ticket = Ticket::factory()->inProgress()->create([
        'category_id' => $this->category->id,
        'priority' => TicketPriority::Medium,
        'lock_version' => 2,
    ]);

    $response = $this->withToken($this->agentToken)
        ->patchJson("/api/v1/tickets/{$ticket->ticket_number}", [
            'priority' => 'high',
            'category_id' => $techCategory->id,
            'lock_version' => 2,
        ]);

    $response->assertOk()
        ->assertJson([
            'priority' => 'high',
            'category' => [
                'id' => $techCategory->id,
                'name' => 'Technical',
            ],
            'lock_version' => 3,
        ]);

    $activities = TicketActivity::where('ticket_id', $ticket->id)->orderBy('id')->get();
    expect($activities->count())->toBe(2);

    $pActivity = $activities->firstWhere('type', TicketActivityType::PriorityChanged);
    expect($pActivity)->not->toBeNull()
        ->and($pActivity->old_values)->toBe(['priority' => 'medium'])
        ->and($pActivity->new_values)->toBe(['priority' => 'high'])
        ->and($pActivity->actor_id)->toBe($this->agent->id);

    $cActivity = $activities->firstWhere('type', TicketActivityType::CategoryChanged);
    expect($cActivity)->not->toBeNull()
        ->and($cActivity->old_values)->toBe(['category' => ['id' => $this->category->id, 'name' => 'Billing']])
        ->and($cActivity->new_values)->toBe(['category' => ['id' => $techCategory->id, 'name' => 'Technical']])
        ->and($cActivity->actor_id)->toBe($this->agent->id);
});

it('produces no activity when values are unchanged', function () {
    $ticket = Ticket::factory()->open()->create([
        'subject' => 'Unchanged subject',
        'priority' => TicketPriority::Medium,
        'lock_version' => 1,
    ]);

    $response = $this->withToken($this->agentToken)
        ->patchJson("/api/v1/tickets/{$ticket->ticket_number}", [
            'subject' => 'Unchanged subject',
            'priority' => 'medium',
            'lock_version' => 1,
        ]);

    $response->assertOk();
    expect(TicketActivity::where('ticket_id', $ticket->id)->count())->toBe(0);
});

it('forbids edits on closed tickets with 403', function () {
    $ticket = Ticket::factory()->closed()->create([
        'subject' => 'Closed ticket',
        'lock_version' => 5,
    ]);

    $this->withToken($this->adminToken)
        ->patchJson("/api/v1/tickets/{$ticket->ticket_number}", [
            'subject' => 'Trying to edit closed ticket',
            'lock_version' => 5,
        ])
        ->assertForbidden();

    expect($ticket->refresh()->subject)->toBe('Closed ticket');
});

it('rejects update requests missing lock_version with 422', function () {
    $ticket = Ticket::factory()->open()->create();

    $this->withToken($this->agentToken)
        ->patchJson("/api/v1/tickets/{$ticket->ticket_number}", [
            'subject' => 'No lock version sent',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['lock_version']);
});

it('rejects stale lock_version with 409 conflict, current ticket, and no side-effects', function () {
    $ticket = Ticket::factory()->open()->create([
        'subject' => 'Current stored subject',
        'priority' => TicketPriority::Medium,
        'lock_version' => 3,
    ]);

    $response = $this->withToken($this->agentToken)
        ->patchJson("/api/v1/tickets/{$ticket->ticket_number}", [
            'subject' => 'Stale update attempt',
            'priority' => 'high',
            'lock_version' => 2, // stale version
        ]);

    $response->assertStatus(409)
        ->assertJson([
            'message' => 'This ticket was changed by someone else. The latest version is now shown.',
            'ticket' => [
                'ticket_number' => $ticket->ticket_number,
                'subject' => 'Current stored subject',
                'priority' => 'medium',
                'lock_version' => 3,
            ],
        ]);

    expect($ticket->refresh()->subject)->toBe('Current stored subject')
        ->and($ticket->priority)->toBe(TicketPriority::Medium)
        ->and($ticket->lock_version)->toBe(3);

    expect(TicketActivity::where('ticket_id', $ticket->id)->count())->toBe(0);
});

