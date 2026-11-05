<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->customer = User::factory()->customer()->create();
    $this->customerToken = $this->customer->createToken('customer')->plainTextToken;

    $this->agent = User::factory()->agent()->create();
    $this->agentToken = $this->agent->createToken('agent')->plainTextToken;

    $this->category = Category::factory()->create();
});

it('lists active tickets by default and under view=active newest first with pagination meta', function () {
    $t1 = Ticket::factory()->open()->create(['created_at' => now()->subMinutes(10)]);
    $t2 = Ticket::factory()->inProgress()->create(['created_at' => now()->subMinutes(8)]);
    $t3 = Ticket::factory()->waitingForCustomer()->create(['created_at' => now()->subMinutes(5)]);
    $resolved = Ticket::factory()->resolved()->create();
    $closed = Ticket::factory()->closed()->create();

    // Default view
    $response = $this->withToken($this->agentToken)->getJson('/api/v1/tickets');
    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'ticket_number', 'subject', 'priority', 'status', 'category', 'customer', 'assigned_agent', 'created_at'],
            ],
            'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
        ]);

    $ticketNumbers = collect($response->json('data'))->pluck('ticket_number')->all();
    expect($ticketNumbers)->toBe([$t3->ticket_number, $t2->ticket_number, $t1->ticket_number]);

    // Explicit view=active
    $responseActive = $this->withToken($this->agentToken)->getJson('/api/v1/tickets?view=active');
    $responseActive->assertOk();
    expect(collect($responseActive->json('data'))->pluck('ticket_number')->all())
        ->toBe([$t3->ticket_number, $t2->ticket_number, $t1->ticket_number]);
});

it('lists closed and resolved tickets under view=closed newest first', function () {
    Ticket::factory()->open()->create();
    $resolved = Ticket::factory()->resolved()->create(['created_at' => now()->subMinutes(5)]);
    $closed = Ticket::factory()->closed()->create(['created_at' => now()->subMinutes(1)]);

    $response = $this->withToken($this->agentToken)->getJson('/api/v1/tickets?view=closed');
    $response->assertOk();

    $ticketNumbers = collect($response->json('data'))->pluck('ticket_number')->all();
    expect($ticketNumbers)->toBe([$closed->ticket_number, $resolved->ticket_number]);
});

it('rejects invalid view with 422', function () {
    $this->withToken($this->agentToken)
        ->getJson('/api/v1/tickets?view=archived')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['view']);
});

it('scopes ticket list to customer own tickets while agents see all', function () {
    $otherCustomer = User::factory()->customer()->create();

    $anaT1 = Ticket::factory()->open()->create(['customer_id' => $this->customer->id]);
    $anaT2 = Ticket::factory()->open()->create(['customer_id' => $this->customer->id]);
    $anaT3 = Ticket::factory()->open()->create(['customer_id' => $this->customer->id]);

    $benT1 = Ticket::factory()->open()->create(['customer_id' => $otherCustomer->id]);
    $benT2 = Ticket::factory()->open()->create(['customer_id' => $otherCustomer->id]);

    // Customer Ana only sees her 3 tickets
    $responseAna = $this->withToken($this->customerToken)->getJson('/api/v1/tickets');
    $responseAna->assertOk();
    $anaIds = collect($responseAna->json('data'))->pluck('id')->all();
    expect(count($anaIds))->toBe(3)
        ->and($anaIds)->toContain($anaT1->id, $anaT2->id, $anaT3->id);

    $this->app['auth']->forgetGuards();

    // Agent sees all 5 tickets
    $responseAgent = $this->withToken($this->agentToken)->getJson('/api/v1/tickets');
    $responseAgent->assertOk();
    expect(count($responseAgent->json('data')))->toBe(5);
});

it('returns 404 when a customer requests another customer ticket detail', function () {
    $otherCustomer = User::factory()->customer()->create();
    $benTicket = Ticket::factory()->open()->create([
        'ticket_number' => 'TKT-2026-000010',
        'customer_id' => $otherCustomer->id,
    ]);

    $this->withToken($this->customerToken)
        ->getJson("/api/v1/tickets/{$benTicket->ticket_number}")
        ->assertNotFound();
});

it('returns ticket detail fields and permissions per role and status', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->customer->id,
        'created_by_id' => $this->customer->id,
        'category_id' => $this->category->id,
    ]);

    // Staff view of open ticket
    $agentResponse = $this->withToken($this->agentToken)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}");

    $agentResponse->assertOk()
        ->assertJson([
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'priority' => 'medium',
            'status' => 'open',
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ],
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
            'permissions' => [
                'can_update_content' => true,
                'can_update_classification' => true,
                'allowed_statuses' => ['in_progress', 'waiting_for_customer', 'resolved', 'closed'],
            ],
        ]);

    // Customer view of resolved ticket
    $ticket->status = TicketStatus::Resolved;
    $ticket->resolved_at = now();
    $ticket->save();

    $this->app['auth']->forgetGuards();

    $customerResponse = $this->withToken($this->customerToken)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}");

    $customerResponse->assertOk()
        ->assertJson([
            'permissions' => [
                'can_update_content' => false,
                'can_update_classification' => false,
                'allowed_statuses' => ['open', 'closed'],
            ],
        ]);
});

it('runs a constant number of database queries regardless of page size', function () {
    // Create 20 tickets with separate categories and customers to detect N+1 queries
    for ($i = 0; $i < 20; $i++) {
        $cat = Category::factory()->create();
        $cust = User::factory()->customer()->create();
        Ticket::factory()->open()->create([
            'category_id' => $cat->id,
            'customer_id' => $cust->id,
        ]);
    }

    // Measure queries with 3 tickets in DB
    TicketActivity::query()->delete();
    Ticket::query()->delete();
    for ($i = 0; $i < 3; $i++) {
        $cat = Category::factory()->create();
        $cust = User::factory()->customer()->create();
        Ticket::factory()->open()->create([
            'category_id' => $cat->id,
            'customer_id' => $cust->id,
        ]);
    }

    // Warm up authentication so Sanctum queries do not pollute query count
    $this->withToken($this->agentToken)->getJson('/api/v1/tickets?view=active');

    // Measure queries with 3 tickets in DB
    TicketActivity::query()->delete();
    Ticket::query()->delete();
    for ($i = 0; $i < 3; $i++) {
        $cat = Category::factory()->create();
        $cust = User::factory()->customer()->create();
        Ticket::factory()->open()->create([
            'category_id' => $cat->id,
            'customer_id' => $cust->id,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->withToken($this->agentToken)->getJson('/api/v1/tickets?view=active');
    $queriesFor3 = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Measure queries with 20 tickets in DB
    TicketActivity::query()->delete();
    Ticket::query()->delete();
    for ($i = 0; $i < 20; $i++) {
        $cat = Category::factory()->create();
        $cust = User::factory()->customer()->create();
        Ticket::factory()->open()->create([
            'category_id' => $cat->id,
            'customer_id' => $cust->id,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->withToken($this->agentToken)->getJson('/api/v1/tickets?view=active');
    $queriesFor20 = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queriesFor20)->toBe($queriesFor3)
        ->and($queriesFor20)->toBe(4);
});

it('filters tickets by priority and category together', function () {
    $billing = Category::factory()->create(['name' => 'Billing']);
    $hardware = Category::factory()->create(['name' => 'Hardware']);

    $highBilling = Ticket::factory()->open()->create([
        'category_id' => $billing->id,
        'priority' => TicketPriority::High,
    ]);
    Ticket::factory()->open()->create([
        'category_id' => $hardware->id,
        'priority' => TicketPriority::High,
    ]);
    Ticket::factory()->open()->create([
        'category_id' => $billing->id,
        'priority' => TicketPriority::Low,
    ]);

    $response = $this->withToken($this->agentToken)
        ->getJson("/api/v1/tickets?priority=high&category_id={$billing->id}");

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$highBilling->id]);
});

it('overrides view tab when explicit status is provided', function () {
    Ticket::factory()->open()->create();
    $resolved = Ticket::factory()->resolved()->create();

    $response = $this->withToken($this->agentToken)
        ->getJson('/api/v1/tickets?view=active&status=resolved');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$resolved->id]);
});

it('rejects invalid filter values with 422 on the respective field', function (string $query, string $field) {
    $this->withToken($this->agentToken)
        ->getJson("/api/v1/tickets?{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    ['priority=urgent', 'priority'],
    ['status=done', 'status'],
    ['category_id=999999', 'category_id'],
]);

it('filters by assigned=me and assigned=unassigned for an agent', function () {
    $mine = Ticket::factory()->open()->create(['assigned_agent_id' => $this->agent->id]);
    $unassigned = Ticket::factory()->open()->create(['assigned_agent_id' => null]);
    $otherAgent = User::factory()->agent()->create();
    Ticket::factory()->open()->create(['assigned_agent_id' => $otherAgent->id]);

    $resMe = $this->withToken($this->agentToken)->getJson('/api/v1/tickets?assigned=me');
    $resMe->assertOk();
    expect(collect($resMe->json('data'))->pluck('id')->all())->toBe([$mine->id]);

    $resUnassigned = $this->withToken($this->agentToken)->getJson('/api/v1/tickets?assigned=unassigned');
    $resUnassigned->assertOk();
    expect(collect($resUnassigned->json('data'))->pluck('id')->all())->toBe([$unassigned->id]);
});

it('filters tickets by single criteria using dataset', function (callable $queryParam, callable $expectedTicket) {
    $cat1 = Category::factory()->create(['name' => 'Billing']);
    $cat2 = Category::factory()->create(['name' => 'Technical']);

    $ticket1 = Ticket::factory()->open()->create([
        'category_id' => $cat1->id,
        'priority' => TicketPriority::Critical,
        'subject' => 'Urgent billing problem',
    ]);
    $ticket2 = Ticket::factory()->resolved()->create([
        'category_id' => $cat2->id,
        'priority' => TicketPriority::Low,
        'subject' => 'Resolved printer setup',
    ]);

    $param = $queryParam($cat1, $cat2);
    $expected = $expectedTicket($ticket1, $ticket2);

    $response = $this->withToken($this->agentToken)->getJson("/api/v1/tickets?{$param}");
    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$expected->id]);
})->with([
    'status resolved' => [
        fn ($cat1, $cat2) => 'status=resolved',
        fn ($t1, $t2) => $t2,
    ],
    'category billing' => [
        fn ($cat1, $cat2) => "category_id={$cat1->id}",
        fn ($t1, $t2) => $t1,
    ],
    'priority critical' => [
        fn ($cat1, $cat2) => 'priority=critical',
        fn ($t1, $t2) => $t1,
    ],
]);
