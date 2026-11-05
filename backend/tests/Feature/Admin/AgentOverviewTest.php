<?php

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->token = $this->admin->createToken('admin-token')->plainTextToken;
});

it('reports active_ticket_count for agent with open, waiting, and closed tickets', function () {
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $category = Category::factory()->create();

    // 2 open tickets
    Ticket::factory()->count(2)->create([
        'assigned_agent_id' => $rafi->id,
        'category_id' => $category->id,
        'status' => TicketStatus::Open,
    ]);

    // 1 waiting ticket
    Ticket::factory()->create([
        'assigned_agent_id' => $rafi->id,
        'category_id' => $category->id,
        'status' => TicketStatus::WaitingForCustomer,
    ]);

    // 4 closed tickets
    Ticket::factory()->count(4)->create([
        'assigned_agent_id' => $rafi->id,
        'category_id' => $category->id,
        'status' => TicketStatus::Closed,
    ]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/admin/agents');

    $response->assertOk()
        ->assertJsonFragment([
            'id' => $rafi->id,
            'name' => 'Rafi',
            'active_ticket_count' => 3,
        ]);
});

it('lists unavailable agents with is_available false, excludes deactivated and non-agents, and sorts by name', function () {
    $category = Category::factory()->create(['name' => 'Support']);

    $zack = User::factory()->agent()->unavailable()->create(['name' => 'Zack']);
    $zack->categories()->attach($category);

    $alice = User::factory()->agent()->create(['name' => 'Alice']);
    $alice->categories()->attach($category);

    // Deactivated agent
    User::factory()->agent()->inactive()->create(['name' => 'Bob']);

    // Non-agent
    User::factory()->customer()->create(['name' => 'Charlie']);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/admin/agents');

    $response->assertOk();

    $names = collect($response->json())->pluck('name')->all();
    expect($names)->toBe(['Alice', 'Zack']);

    $zackData = collect($response->json())->firstWhere('name', 'Zack');
    expect($zackData['is_available'])->toBeFalse()
        ->and($zackData['categories'])->toHaveCount(1)
        ->and($zackData['categories'][0]['id'])->toBe($category->id);
});

it('forbids agent from accessing agents overview with 403', function () {
    $agent = User::factory()->agent()->create();
    $token = $agent->createToken('agent-token')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/admin/agents');

    $response->assertForbidden();
});

it('runs constant number of queries regardless of agent count', function () {
    $category = Category::factory()->create();

    // Warm up sanctum auth so auth queries do not pollute the count
    $this->withToken($this->token)->getJson('/api/v1/admin/agents')->assertOk();

    // Create 2 agents
    $agents2 = User::factory()->agent()->count(2)->create();
    foreach ($agents2 as $a) {
        $a->categories()->attach($category);
    }

    DB::enableQueryLog();
    DB::flushQueryLog();

    $this->withToken($this->token)->getJson('/api/v1/admin/agents')->assertOk();
    $queryCount2 = count(DB::getQueryLog());

    // Create 8 more agents (10 total)
    $agents8 = User::factory()->agent()->count(8)->create();
    foreach ($agents8 as $a) {
        $a->categories()->attach($category);
    }

    DB::flushQueryLog();

    $this->withToken($this->token)->getJson('/api/v1/admin/agents')->assertOk();
    $queryCount10 = count(DB::getQueryLog());

    expect($queryCount10)->toBe($queryCount2)
        ->and($queryCount2)->toBe(3);
});
