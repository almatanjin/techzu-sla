<?php

use App\Enums\TicketActivityType;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->customer = User::factory()->customer()->create();
    $this->customerToken = $this->customer->createToken('customer')->plainTextToken;

    $this->otherCustomer = User::factory()->customer()->create();
    $this->otherToken = $this->otherCustomer->createToken('other')->plainTextToken;

    $this->agent = User::factory()->agent()->create();
    $this->agentToken = $this->agent->createToken('agent')->plainTextToken;

    $this->category = Category::factory()->create(['name' => 'Billing']);
});

it('returns activities in chronological order (oldest first) with expected contents', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
    ]);

    $activity1 = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'actor_id' => $this->customer->id,
        'type' => TicketActivityType::Created,
        'created_at' => now()->subMinutes(10),
    ]);

    $activity2 = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'actor_id' => $this->agent->id,
        'type' => TicketActivityType::StatusChanged,
        'old_values' => ['status' => 'open'],
        'new_values' => ['status' => 'in_progress'],
        'created_at' => now()->subMinutes(5),
    ]);

    $response = $this->withToken($this->customerToken)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities");

    $response->assertOk();
    $data = $response->json();

    expect(count($data))->toBe(2)
        ->and($data[0]['id'])->toBe($activity1->id)
        ->and($data[0]['type'])->toBe('created')
        ->and($data[0]['actor']['id'])->toBe($this->customer->id)
        ->and($data[0]['actor']['name'])->toBe($this->customer->name)
        ->and($data[1]['id'])->toBe($activity2->id)
        ->and($data[1]['type'])->toBe('status_changed')
        ->and($data[1]['old_values'])->toBe(['status' => 'open'])
        ->and($data[1]['new_values'])->toBe(['status' => 'in_progress']);
});

it('represents System actor as null in activity payload', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
    ]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'actor_id' => null,
        'type' => TicketActivityType::Created,
    ]);

    $response = $this->withToken($this->customerToken)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities");

    $response->assertOk();
    expect($response->json('0.actor'))->toBeNull();
});

it('preserves historical category names even after the category is renamed', function () {
    $tech = Category::factory()->create(['name' => 'Technical']);

    $ticket = Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'customer_id' => $this->customer->id,
    ]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'actor_id' => $this->agent->id,
        'type' => TicketActivityType::CategoryChanged,
        'old_values' => ['category' => ['id' => $this->category->id, 'name' => 'Billing']],
        'new_values' => ['category' => ['id' => $tech->id, 'name' => 'Technical']],
    ]);

    // Admin renames the category in the database
    $tech->name = 'Tech Support';
    $tech->save();

    $response = $this->withToken($this->customerToken)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities");

    $response->assertOk();
    expect($response->json('0.new_values.category.name'))->toBe('Technical');
});

it('returns 404 when another customer requests activities of a ticket', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
    ]);

    $this->withToken($this->otherToken)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities")
        ->assertNotFound();
});

it('rejects write HTTP verbs on activities endpoint with 405 Method Not Allowed', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
    ]);

    $this->withToken($this->customerToken)
        ->patchJson("/api/v1/tickets/{$ticket->ticket_number}/activities", [])
        ->assertStatus(405);

    $this->withToken($this->customerToken)
        ->putJson("/api/v1/tickets/{$ticket->ticket_number}/activities", [])
        ->assertStatus(405);

    $this->withToken($this->customerToken)
        ->deleteJson("/api/v1/tickets/{$ticket->ticket_number}/activities")
        ->assertStatus(405);
});

it('runs a constant number of database queries for 2 and 15 activity entries', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
    ]);

    // Warm up auth
    $this->withToken($this->agentToken)->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities");

    // Test with 2 activities
    TicketActivity::factory()->count(2)->create(['ticket_id' => $ticket->id]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->withToken($this->agentToken)->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities");
    $queriesFor2 = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Add 13 more (total 15)
    TicketActivity::factory()->count(13)->create(['ticket_id' => $ticket->id]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->withToken($this->agentToken)->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities");
    $queriesFor15 = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queriesFor15)->toBe($queriesFor2);
});

it('omits sla_warning and escalated activities from customer responses while retaining system unassigned and all entries for staff', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
    ]);

    $created = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'actor_id' => $this->customer->id,
        'type' => TicketActivityType::Created,
        'created_at' => now()->subMinutes(20),
    ]);

    $slaWarning = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'actor_id' => null,
        'type' => TicketActivityType::SlaWarning,
        'created_at' => now()->subMinutes(15),
    ]);

    $unassigned = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'actor_id' => null,
        'type' => TicketActivityType::Unassigned,
        'created_at' => now()->subMinutes(10),
    ]);

    $escalated = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'actor_id' => null,
        'type' => TicketActivityType::Escalated,
        'created_at' => now()->subMinutes(5),
    ]);

    // Customer requests activities
    $this->app['auth']->forgetGuards();
    $custRes = $this->withToken($this->customerToken)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities");
    $custRes->assertOk();
    $custData = $custRes->json();
    $custTypes = array_column($custData, 'type');

    expect($custTypes)->toBe(['created', 'unassigned'])
        ->and($custTypes)->not->toContain('sla_warning')
        ->and($custTypes)->not->toContain('escalated');

    // Agent requests activities
    $this->app['auth']->forgetGuards();
    $agentRes = $this->withToken($this->agentToken)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities");
    $agentRes->assertOk();
    $agentData = $agentRes->json();
    $agentTypes = array_column($agentData, 'type');

    expect($agentTypes)->toBe(['created', 'sla_warning', 'unassigned', 'escalated']);
});


