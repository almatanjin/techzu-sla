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

    $this->category = Category::factory()->create(['is_active' => true]);
});

it('allows customer to create a ticket with default medium priority', function () {
    $response = $this->withToken($this->customerToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Cannot access billing portal',
            'description' => 'I get a 500 error whenever I click on invoices.',
            'category_id' => $this->category->id,
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'subject' => 'Cannot access billing portal',
            'description' => 'I get a 500 error whenever I click on invoices.',
            'status' => 'open',
            'priority' => 'medium',
            'customer' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ],
            'created_by' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ],
            'assigned_agent' => null,
            'lock_version' => 0,
        ]);

    expect($response->json('ticket_number'))->toMatch('/^TKT-\d{4}-\d{6}$/');
});

it('ignores customer_id sent by customer and assigns to themselves', function () {
    $otherCustomer = User::factory()->customer()->create();

    $response = $this->withToken($this->customerToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Sneaky ticket',
            'description' => 'Trying to attribute to other user.',
            'category_id' => $this->category->id,
            'customer_id' => $otherCustomer->id,
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'customer' => [
                'id' => $this->customer->id,
            ],
        ]);
});

it('allows staff to create ticket on behalf of an active customer', function () {
    $ana = User::factory()->customer()->create(['name' => 'Ana']);

    $response = $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Phone call from customer',
            'description' => 'Ana called regarding login issues.',
            'category_id' => $this->category->id,
            'customer_id' => $ana->id,
            'priority' => 'high',
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'priority' => 'high',
            'customer' => [
                'id' => $ana->id,
                'name' => 'Ana',
            ],
            'created_by' => [
                'id' => $this->agent->id,
                'name' => $this->agent->name,
            ],
        ]);
});

it('rejects staff ticket creation when customer_id is missing or invalid', function () {
    $otherAgent = User::factory()->agent()->create();
    $otherAdmin = User::factory()->admin()->create();
    $inactiveCustomer = User::factory()->customer()->inactive()->create();

    // Missing customer_id
    $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Missing customer',
            'description' => 'Forgot customer id',
            'category_id' => $this->category->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['customer_id']);

    // Non-existent customer_id
    $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Unknown customer',
            'description' => 'Unknown',
            'category_id' => $this->category->id,
            'customer_id' => 999999,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['customer_id']);

    // Agent as customer_id
    $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Agent as customer',
            'description' => 'Invalid',
            'category_id' => $this->category->id,
            'customer_id' => $otherAgent->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['customer_id']);

    // Admin as customer_id
    $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Admin as customer',
            'description' => 'Invalid',
            'category_id' => $this->category->id,
            'customer_id' => $otherAdmin->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['customer_id']);

    // Inactive customer as customer_id
    $this->withToken($this->agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Inactive customer',
            'description' => 'Invalid',
            'category_id' => $this->category->id,
            'customer_id' => $inactiveCustomer->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['customer_id']);
});

it('rejects ticket creation with inactive category', function () {
    $inactiveCategory = Category::factory()->inactive()->create();

    $this->withToken($this->customerToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Test',
            'description' => 'Test description',
            'category_id' => $inactiveCategory->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['category_id']);
});

it('validates subject and description field limits', function () {
    // Subject > 200
    $this->withToken($this->customerToken)
        ->postJson('/api/v1/tickets', [
            'subject' => str_repeat('a', 201),
            'description' => 'Valid description',
            'category_id' => $this->category->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['subject']);

    // Description > 10,000
    $this->withToken($this->customerToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Valid subject',
            'description' => str_repeat('b', 10001),
            'category_id' => $this->category->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['description']);
});

it('records a created activity entry upon ticket creation', function () {
    $response = $this->withToken($this->customerToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Check activity logging',
            'description' => 'Ensuring created activity is written',
            'category_id' => $this->category->id,
        ]);

    $response->assertStatus(201);
    $ticketNumber = $response->json('ticket_number');

    $ticket = Ticket::where('ticket_number', $ticketNumber)->firstOrFail();

    $activity = TicketActivity::where('ticket_id', $ticket->id)->first();
    expect($activity)->not->toBeNull()
        ->and($activity->type)->toBe(TicketActivityType::Created)
        ->and($activity->actor_id)->toBe($this->customer->id)
        ->and($activity->old_values)->toBeNull()
        ->and($activity->new_values)->toBeNull();
});

