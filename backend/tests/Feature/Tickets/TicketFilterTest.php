<?php

use App\Enums\TicketPriority;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->adminToken = $this->admin->createToken('admin')->plainTextToken;

    $this->category = Category::factory()->create();
});

test("an admin's assigned filter with an agent id lists exactly that agent's tickets", function () {
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $mina = User::factory()->agent()->create(['name' => 'Mina']);

    $r1 = Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $rafi->id,
    ]);
    $r2 = Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $rafi->id,
    ]);
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $mina->id,
    ]);

    $response = $this->withToken($this->adminToken)
        ->getJson("/api/v1/tickets?assigned={$rafi->id}");

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$r2->id, $r1->id]);
});

test("an admin's assigned filter with a deactivated agent id lists that agent's tickets", function () {
    $deactivated = User::factory()->agent()->create([
        'name' => 'Old Agent',
        'is_active' => false,
    ]);

    $t1 = Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $deactivated->id,
    ]);

    $response = $this->withToken($this->adminToken)
        ->getJson("/api/v1/tickets?assigned={$deactivated->id}");

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$t1->id]);
});

test("an agent's assigned filter with an agent id is ignored and lists all tickets", function () {
    $mina = User::factory()->agent()->create(['name' => 'Mina']);
    $minaToken = $mina->createToken('mina')->plainTextToken;
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);

    $r1 = Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $rafi->id,
    ]);
    $m1 = Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $mina->id,
    ]);

    $withoutFilter = $this->withToken($minaToken)->getJson('/api/v1/tickets');
    $withFilter = $this->withToken($minaToken)->getJson("/api/v1/tickets?assigned={$rafi->id}");

    $withoutFilter->assertOk();
    $withFilter->assertOk();
    expect($withFilter->json('data'))->toBe($withoutFilter->json('data'))
        ->and(collect($withFilter->json('data'))->pluck('id')->all())->toContain($r1->id, $m1->id);
});

test('assigned filter rejects customer ids and invalid formats with 422', function () {
    $ana = User::factory()->customer()->create();

    $this->withToken($this->adminToken)
        ->getJson("/api/v1/tickets?assigned={$ana->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned']);

    $this->withToken($this->adminToken)
        ->getJson('/api/v1/tickets?assigned=abc')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned']);
});

test("an agent's customer_id filter lists exactly that customer's tickets", function () {
    $agent = User::factory()->agent()->create();
    $agentToken = $agent->createToken('agent')->plainTextToken;

    $ana = User::factory()->customer()->create(['name' => 'Ana']);
    $ben = User::factory()->customer()->create(['name' => 'Ben']);

    $a1 = Ticket::factory()->open()->create(['customer_id' => $ana->id, 'category_id' => $this->category->id]);
    $a2 = Ticket::factory()->inProgress()->create(['customer_id' => $ana->id, 'category_id' => $this->category->id]);
    $a3 = Ticket::factory()->resolved()->create(['customer_id' => $ana->id, 'category_id' => $this->category->id]);

    Ticket::factory()->open()->create(['customer_id' => $ben->id, 'category_id' => $this->category->id]);
    Ticket::factory()->resolved()->create(['customer_id' => $ben->id, 'category_id' => $this->category->id]);

    $response = $this->withToken($agentToken)
        ->getJson("/api/v1/tickets?customer_id={$ana->id}&view=all");

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toHaveCount(3)
        ->and($ids)->toContain($a1->id, $a2->id, $a3->id);
});

test("customer sending another customer's id ignores customer_id and lists only own tickets", function () {
    $ana = User::factory()->customer()->create(['name' => 'Ana']);
    $anaToken = $ana->createToken('ana')->plainTextToken;
    $ben = User::factory()->customer()->create(['name' => 'Ben']);

    $a1 = Ticket::factory()->open()->create(['customer_id' => $ana->id, 'category_id' => $this->category->id]);
    Ticket::factory()->open()->create(['customer_id' => $ben->id, 'category_id' => $this->category->id]);

    $response = $this->withToken($anaToken)
        ->getJson("/api/v1/tickets?customer_id={$ben->id}");

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$a1->id]);
});

test("customer_id with a staff id answers 422 on customer_id", function () {
    $agent = User::factory()->agent()->create();
    $agentToken = $agent->createToken('agent')->plainTextToken;
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);

    $this->withToken($agentToken)
        ->getJson("/api/v1/tickets?customer_id={$rafi->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_id']);
});

test('filters tickets by created date range in business timezone inclusive of whole days', function () {
    $tz = config('tickets.timezone', 'Asia/Dhaka');

    $t1Time = \Carbon\CarbonImmutable::parse('2026-11-01 00:10:00', $tz)->utc();
    $t2Time = \Carbon\CarbonImmutable::parse('2026-11-03 23:50:00', $tz)->utc();
    $t3Time = \Carbon\CarbonImmutable::parse('2026-11-04 00:05:00', $tz)->utc();

    $t1 = Ticket::factory()->open()->create([
        'created_at' => $t1Time,
        'category_id' => $this->category->id,
    ]);
    $t2 = Ticket::factory()->open()->create([
        'created_at' => $t2Time,
        'category_id' => $this->category->id,
    ]);
    $t3 = Ticket::factory()->open()->create([
        'created_at' => $t3Time,
        'category_id' => $this->category->id,
    ]);

    $response = $this->withToken($this->adminToken)
        ->getJson('/api/v1/tickets?created_from=2026-11-01&created_to=2026-11-03');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$t2->id, $t1->id]);
});

test('filters tickets by created_from only and created_to only', function () {
    $tz = config('tickets.timezone', 'Asia/Dhaka');

    $t1Time = \Carbon\CarbonImmutable::parse('2026-11-01 00:10:00', $tz)->utc();
    $t2Time = \Carbon\CarbonImmutable::parse('2026-11-03 23:50:00', $tz)->utc();
    $t3Time = \Carbon\CarbonImmutable::parse('2026-11-04 00:05:00', $tz)->utc();

    $t1 = Ticket::factory()->open()->create(['created_at' => $t1Time, 'category_id' => $this->category->id]);
    $t2 = Ticket::factory()->open()->create(['created_at' => $t2Time, 'category_id' => $this->category->id]);
    $t3 = Ticket::factory()->open()->create(['created_at' => $t3Time, 'category_id' => $this->category->id]);

    $resFrom = $this->withToken($this->adminToken)->getJson('/api/v1/tickets?created_from=2026-11-04');
    $resFrom->assertOk();
    expect(collect($resFrom->json('data'))->pluck('id')->all())->toBe([$t3->id]);

    $resTo = $this->withToken($this->adminToken)->getJson('/api/v1/tickets?created_to=2026-10-31');
    $resTo->assertOk();
    expect($resTo->json('data'))->toBe([]);
});

test('date validation rejects reversed date ranges and non-Y-m-d formats with 422', function () {
    $this->withToken($this->adminToken)
        ->getJson('/api/v1/tickets?created_from=2026-11-05&created_to=2026-11-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['created_to']);

    $this->withToken($this->adminToken)
        ->getJson('/api/v1/tickets?created_from=05/11/2026')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['created_from']);
});

test('sorting by priority orders by rank ascending and descending', function () {
    $low = Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'priority' => TicketPriority::Low,
    ]);
    $med = Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'priority' => TicketPriority::Medium,
    ]);
    $crit = Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'priority' => TicketPriority::Critical,
    ]);

    $resDesc = $this->withToken($this->adminToken)->getJson('/api/v1/tickets?sort=-priority');
    $resDesc->assertOk();
    $descIds = collect($resDesc->json('data'))->pluck('id')->all();
    expect($descIds)->toBe([$crit->id, $med->id, $low->id]);

    $resAsc = $this->withToken($this->adminToken)->getJson('/api/v1/tickets?sort=priority');
    $resAsc->assertOk();
    $ascIds = collect($resAsc->json('data'))->pluck('id')->all();
    expect($ascIds)->toBe([$low->id, $med->id, $crit->id]);
});

test('sorting by sla_due_at orders earliest due first for an agent', function () {
    $agent = User::factory()->agent()->create();
    $agentToken = $agent->createToken('agent')->plainTextToken;

    $t1 = Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'sla_due_at' => now()->addHours(5),
    ]);
    $t2 = Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'sla_due_at' => now()->addHours(2),
    ]);
    $t3 = Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'sla_due_at' => now()->addHours(8),
    ]);

    $response = $this->withToken($agentToken)->getJson('/api/v1/tickets?sort=sla_due_at');
    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$t2->id, $t1->id, $t3->id]);
});

test("customer sorting by sla_due_at is ignored and lists newest created first", function () {
    $ana = User::factory()->customer()->create(['name' => 'Ana']);
    $anaToken = $ana->createToken('ana')->plainTextToken;

    $t1 = Ticket::factory()->open()->create([
        'customer_id' => $ana->id,
        'category_id' => $this->category->id,
        'created_at' => now()->subHours(2),
        'sla_due_at' => now()->addHours(10),
    ]);
    $t2 = Ticket::factory()->open()->create([
        'customer_id' => $ana->id,
        'category_id' => $this->category->id,
        'created_at' => now()->subHour(),
        'sla_due_at' => now()->addHours(2),
    ]);

    $response = $this->withToken($anaToken)->getJson('/api/v1/tickets?sort=sla_due_at');
    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$t2->id, $t1->id]);
});

test('sorting by created_at and -updated_at', function () {
    $t1 = Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'created_at' => now()->subHours(3),
        'updated_at' => now()->subHours(1),
    ]);
    $t2 = Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
    ]);

    $resCreated = $this->withToken($this->adminToken)->getJson('/api/v1/tickets?sort=created_at');
    $resCreated->assertOk();
    $createdIds = collect($resCreated->json('data'))->pluck('id')->all();
    expect($createdIds)->toBe([$t1->id, $t2->id]);

    $resUpdated = $this->withToken($this->adminToken)->getJson('/api/v1/tickets?sort=-updated_at');
    $resUpdated->assertOk();
    $updatedIds = collect($resUpdated->json('data'))->pluck('id')->all();
    expect($updatedIds)->toBe([$t1->id, $t2->id]);
});

test('invalid sort value answers 422 on sort', function () {
    $this->withToken($this->adminToken)
        ->getJson('/api/v1/tickets?sort=subject')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort']);
});

test('per_page parameter controls page size and pagination metadata', function () {
    for ($i = 0; $i < 60; $i++) {
        Ticket::factory()->open()->create([
            'category_id' => $this->category->id,
        ]);
    }

    $response = $this->withToken($this->adminToken)->getJson('/api/v1/tickets?per_page=50');
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(50)
        ->and($response->json('meta.last_page'))->toBe(2)
        ->and($response->json('meta.per_page'))->toBe(50);
});

test('default listing without per_page lists 20 tickets with meta.per_page 20', function () {
    for ($i = 0; $i < 25; $i++) {
        Ticket::factory()->open()->create([
            'category_id' => $this->category->id,
        ]);
    }

    $response = $this->withToken($this->adminToken)->getJson('/api/v1/tickets');
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(20)
        ->and($response->json('meta.per_page'))->toBe(20);
});

test('per_page rejects values below 10 or above 100 with 422', function () {
    $this->withToken($this->adminToken)
        ->getJson('/api/v1/tickets?per_page=9')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);

    $this->withToken($this->adminToken)
        ->getJson('/api/v1/tickets?per_page=500')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);
});
