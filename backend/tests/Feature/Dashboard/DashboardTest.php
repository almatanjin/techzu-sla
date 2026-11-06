<?php

use App\Dashboard\DashboardService;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->category = Category::factory()->create();
});

test('tracer: agent dashboard returns team counts and generated_at for mixed tickets', function () {
    $now = CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC');
    CarbonImmutable::setTestNow($now);

    $agent = User::factory()->agent()->create(['name' => 'Rafi']);
    $otherAgent = User::factory()->agent()->create(['name' => 'Mina']);

    // 1 Critical unassigned and breached
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'priority' => TicketPriority::Critical,
        'assigned_agent_id' => null,
        'sla_warning_at' => $now->subHours(2),
        'sla_due_at' => $now->subMinutes(10),
    ]);

    // 1 High assigned and approaching
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'priority' => TicketPriority::High,
        'assigned_agent_id' => $otherAgent->id,
        'sla_warning_at' => $now->subMinutes(10),
        'sla_due_at' => $now->addHour(),
    ]);

    // 1 Low assigned and within
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'priority' => TicketPriority::Low,
        'assigned_agent_id' => $otherAgent->id,
        'sla_warning_at' => $now->addHour(),
        'sla_due_at' => $now->addHours(2),
    ]);

    // 1 resolved Critical ticket
    Ticket::factory()->resolved()->create([
        'category_id' => $this->category->id,
        'priority' => TicketPriority::Critical,
        'assigned_agent_id' => $otherAgent->id,
        'resolved_at' => $now->subHour(),
    ]);

    $token = $agent->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/dashboard');

    $response->assertOk();
    $response->assertJsonPath('team.open', 3);
    $response->assertJsonPath('team.unassigned', 1);
    $response->assertJsonPath('team.critical', 1);
    $response->assertJsonPath('team.sla_breached', 1);
    $response->assertJsonPath('team.sla_approaching', 1);
    $response->assertJsonPath('team.sla_within', 1);
    expect($response->json('generated_at'))->not->toBeNull();
});

test('team sla_within, sla_approaching and sla_breached add up to open, with the warning and due times as boundaries', function () {
    $now = CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC');
    CarbonImmutable::setTestNow($now);

    $agent = User::factory()->agent()->create();

    // Warning time exactly now: approaching
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'sla_warning_at' => $now,
        'sla_due_at' => $now->addHour(),
    ]);
    // Due time exactly now: breached
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'sla_warning_at' => $now->subHour(),
        'sla_due_at' => $now,
    ]);
    // Warning one second from now: within
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'status' => TicketStatus::WaitingForCustomer,
        'sla_warning_at' => $now->addSecond(),
        'sla_due_at' => $now->addHour(),
    ]);
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'status' => TicketStatus::InProgress,
        'sla_warning_at' => $now->addHours(3),
        'sla_due_at' => $now->addHours(4),
    ]);
    // A closed ticket with a future warning time is not counted
    Ticket::factory()->create([
        'category_id' => $this->category->id,
        'status' => TicketStatus::Closed,
        'closed_at' => $now->subHour(),
        'sla_warning_at' => $now->addHours(3),
        'sla_due_at' => $now->addHours(4),
    ]);

    $token = $agent->createToken('test')->plainTextToken;

    $team = $this->withToken($token)->getJson('/api/v1/dashboard')->assertOk()->json('team');

    expect($team['open'])->toBe(4);
    expect($team['sla_within'])->toBe(2);
    expect($team['sla_approaching'])->toBe(1);
    expect($team['sla_breached'])->toBe(1);
    expect($team['sla_within'] + $team['sla_approaching'] + $team['sla_breached'])->toBe($team['open']);
});

test('by_priority lists active counts in order critical, high, medium, low', function () {
    $agent = User::factory()->agent()->create();

    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'priority' => TicketPriority::Critical,
    ]);
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'priority' => TicketPriority::Critical,
    ]);
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'priority' => TicketPriority::Low,
    ]);

    $token = $agent->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/dashboard');

    $response->assertOk();
    expect($response->json('by_priority'))->toBe([
        ['priority' => 'critical', 'count' => 2],
        ['priority' => 'high', 'count' => 0],
        ['priority' => 'medium', 'count' => 0],
        ['priority' => 'low', 'count' => 1],
    ]);
});

test('day boundaries in business timezone gives resolved_today 1 and resolved_last_7_days 3', function () {
    $tz = config('tickets.timezone', 'Asia/Dhaka');
    $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00:00', $tz));

    $agent = User::factory()->agent()->create();

    // 2026-11-05 00:30 Asia/Dhaka
    Ticket::factory()->resolved()->create([
        'category_id' => $this->category->id,
        'resolved_at' => CarbonImmutable::parse('2026-11-05 00:30:00', $tz)->setTimezone('UTC'),
    ]);

    // 2026-11-04 23:30 Asia/Dhaka
    Ticket::factory()->resolved()->create([
        'category_id' => $this->category->id,
        'resolved_at' => CarbonImmutable::parse('2026-11-04 23:30:00', $tz)->setTimezone('UTC'),
    ]);

    // 2026-10-30 09:00 Asia/Dhaka
    Ticket::factory()->resolved()->create([
        'category_id' => $this->category->id,
        'resolved_at' => CarbonImmutable::parse('2026-10-30 09:00:00', $tz)->setTimezone('UTC'),
    ]);

    // 2026-10-29 23:00 Asia/Dhaka
    Ticket::factory()->resolved()->create([
        'category_id' => $this->category->id,
        'resolved_at' => CarbonImmutable::parse('2026-10-29 23:00:00', $tz)->setTimezone('UTC'),
    ]);

    $token = $agent->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/dashboard');

    $response->assertOk();
    $response->assertJsonPath('team.resolved_today', 1);
    $response->assertJsonPath('team.resolved_last_7_days', 3);
});

test('a ticket resolved today then closed counts in resolved_today, and a reopened one does not', function () {
    $tz = config('tickets.timezone', 'Asia/Dhaka');
    $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00:00', $tz));

    $agent = User::factory()->agent()->create();

    // Resolved today then closed an hour later
    Ticket::factory()->create([
        'category_id' => $this->category->id,
        'status' => TicketStatus::Closed,
        'resolved_at' => CarbonImmutable::parse('2026-11-05 08:00:00', $tz)->setTimezone('UTC'),
        'closed_at' => CarbonImmutable::parse('2026-11-05 09:00:00', $tz)->setTimezone('UTC'),
    ]);

    // Reopened ticket: resolved_at was cleared to null
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'resolved_at' => null,
    ]);

    $token = $agent->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/dashboard');

    $response->assertOk();
    $response->assertJsonPath('team.resolved_today', 1);
});

test('a request without a token answers 401', function () {
    $response = $this->getJson('/api/v1/dashboard');

    $response->assertUnauthorized();
});

test('my_queue counts own active, approaching and breached tickets and agent has no by_agent', function () {
    $now = CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC');
    CarbonImmutable::setTestNow($now);

    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $mina = User::factory()->agent()->create(['name' => 'Mina']);

    // Rafi has 3 active tickets, 1 breached, 0 approaching (2 within)
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $rafi->id,
        'sla_warning_at' => $now->subHours(2),
        'sla_due_at' => $now->subMinute(), // breached
    ]);
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $rafi->id,
        'sla_warning_at' => $now->addHours(2),
        'sla_due_at' => $now->addHours(4), // within
    ]);
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $rafi->id,
        'sla_warning_at' => $now->addHours(1),
        'sla_due_at' => $now->addHours(3), // within
    ]);

    // Mina has 2 active tickets
    Ticket::factory()->open()->count(2)->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $mina->id,
    ]);

    $token = $rafi->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/dashboard');

    $response->assertOk();
    $response->assertJsonPath('my_queue.open', 3);
    $response->assertJsonPath('my_queue.sla_approaching', 0);
    $response->assertJsonPath('my_queue.sla_breached', 1);
    $response->assertJsonMissingPath('by_agent');
});

test('admin dashboard lists by_agent workload table sorted by name with active agents and ticket holders', function () {
    $now = CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC');
    CarbonImmutable::setTestNow($now);

    $admin = User::factory()->admin()->create(['name' => 'Admin Boss']);
    $mina = User::factory()->agent()->create(['name' => 'Mina']);
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $sam = User::factory()->agent()->create(['name' => 'Sam', 'is_active' => false]);

    // Mina: 2 active tickets, 1 breached, 0 approaching (1 within)
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $mina->id,
        'sla_warning_at' => $now->subHours(2),
        'sla_due_at' => $now->subMinute(),
    ]);
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $mina->id,
        'sla_warning_at' => $now->addHours(2),
        'sla_due_at' => $now->addHours(4),
    ]);

    // Rafi: no tickets

    // Sam (deactivated): 1 active ticket
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $sam->id,
        'sla_warning_at' => $now->addHours(2),
        'sla_due_at' => $now->addHours(4),
    ]);

    // Admin: 1 active ticket
    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $admin->id,
        'sla_warning_at' => $now->addHours(2),
        'sla_due_at' => $now->addHours(4),
    ]);

    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/dashboard');

    $response->assertOk();
    $byAgent = $response->json('by_agent');
    expect($byAgent)->toBeArray();
    expect(array_keys($response->json()))->toEqualCanonicalizing(['generated_at', 'team', 'by_priority', 'daily', 'by_agent']);

    // Sorted by name: Admin Boss, Mina, Rafi, Sam
    expect(array_column($byAgent, 'name'))->toBe(['Admin Boss', 'Mina', 'Rafi', 'Sam']);

    // Admin Boss
    expect($byAgent[0])->toMatchArray([
        'id' => $admin->id,
        'name' => 'Admin Boss',
        'is_active' => true,
        'open' => 1,
        'sla_within' => 1,
        'sla_approaching' => 0,
        'sla_breached' => 0,
    ]);

    // Mina
    expect($byAgent[1])->toMatchArray([
        'id' => $mina->id,
        'name' => 'Mina',
        'is_active' => true,
        'open' => 2,
        'sla_within' => 1,
        'sla_approaching' => 0,
        'sla_breached' => 1,
    ]);

    // Rafi
    expect($byAgent[2])->toMatchArray([
        'id' => $rafi->id,
        'name' => 'Rafi',
        'is_active' => true,
        'open' => 0,
        'sla_within' => 0,
        'sla_approaching' => 0,
        'sla_breached' => 0,
    ]);

    // Sam
    expect($byAgent[3])->toMatchArray([
        'id' => $sam->id,
        'name' => 'Sam',
        'is_active' => false,
        'open' => 1,
        'sla_within' => 1,
        'sla_approaching' => 0,
        'sla_breached' => 0,
    ]);
});

test('an admin gets no my_queue and finds their own tickets in by_agent', function () {
    $admin = User::factory()->admin()->create(['name' => 'Admin Boss']);

    Ticket::factory()->open()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $admin->id,
    ]);

    $response = $this->withToken($admin->createToken('test')->plainTextToken)
        ->getJson('/api/v1/dashboard')
        ->assertOk();

    $response->assertJsonMissingPath('my_queue');
    expect(collect($response->json('by_agent'))->firstWhere('id', $admin->id)['open'])->toBe(1);
});

test('by_agent lists agent with only a resolved ticket with open 0', function () {
    $now = CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC');
    CarbonImmutable::setTestNow($now);

    $admin = User::factory()->admin()->create();
    $mina = User::factory()->agent()->create(['name' => 'Mina']);

    Ticket::factory()->resolved()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $mina->id,
    ]);

    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/dashboard');

    $response->assertOk();
    $byAgent = collect($response->json('by_agent'));
    $minaRow = $byAgent->firstWhere('id', $mina->id);
    expect($minaRow)->not->toBeNull();
    expect($minaRow['open'])->toBe(0);
});

test("an agent's response has no by_agent key", function () {
    $agent = User::factory()->agent()->create();
    $token = $agent->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/dashboard');

    $response->assertOk();
    $response->assertJsonMissingPath('by_agent');
    expect(array_keys($response->json()))->toEqualCanonicalizing(['generated_at', 'team', 'my_queue', 'by_priority', 'daily']);
    expect($response->json('daily'))->toHaveCount(14);
});

test('customer dashboard returns summary with own tickets only', function () {
    $ana = User::factory()->customer()->create(['name' => 'Ana']);
    $ben = User::factory()->customer()->create(['name' => 'Ben']);

    // Ana: 2 open, 1 waiting_for_customer, 1 closed
    Ticket::factory()->open()->count(2)->create([
        'category_id' => $this->category->id,
        'customer_id' => $ana->id,
    ]);
    Ticket::factory()->waitingForCustomer()->create([
        'category_id' => $this->category->id,
        'customer_id' => $ana->id,
    ]);
    Ticket::factory()->create([
        'category_id' => $this->category->id,
        'customer_id' => $ana->id,
        'status' => TicketStatus::Closed,
        'closed_at' => now(),
    ]);

    // Ben: 4 open tickets
    Ticket::factory()->open()->count(4)->create([
        'category_id' => $this->category->id,
        'customer_id' => $ben->id,
    ]);

    $token = $ana->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/dashboard');

    $response->assertOk();
    $response->assertJsonPath('summary.open', 3);
    $response->assertJsonPath('summary.waiting_for_customer', 1);
    $response->assertJsonPath('summary.closed', 1);
});

test('customer dashboard returns 5 most recently updated tickets with exact keys and no staff or sla data', function () {
    $ana = User::factory()->customer()->create(['name' => 'Ana']);

    $now = CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC');
    CarbonImmutable::setTestNow($now);

    // Create 7 tickets for Ana with distinct updated_at times
    $tickets = [];
    for ($i = 1; $i <= 7; $i++) {
        $t = Ticket::factory()->open()->create([
            'category_id' => $this->category->id,
            'customer_id' => $ana->id,
            'ticket_number' => sprintf('TKT-2026-%06d', $i),
            'subject' => "Subject {$i}",
            'updated_at' => $now->subMinutes(10 * (8 - $i)), // ticket 7 is newest, ticket 1 oldest
        ]);
        $tickets[$i] = $t;
    }

    $token = $ana->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/api/v1/dashboard');

    $response->assertOk();
    $recent = $response->json('recent_tickets');
    expect($recent)->toBeArray()->toHaveCount(5);

    // Newest first: 7, 6, 5, 4, 3
    expect(array_column($recent, 'ticket_number'))->toBe([
        'TKT-2026-000007',
        'TKT-2026-000006',
        'TKT-2026-000005',
        'TKT-2026-000004',
        'TKT-2026-000003',
    ]);

    // Each ticket has exactly ticket_number, subject, status, updated_at
    foreach ($recent as $item) {
        expect(array_keys($item))->toBe(['ticket_number', 'subject', 'status', 'updated_at']);
    }

    // Customer body has none of team, my_queue, by_priority, by_agent
    $response->assertJsonMissingPath('team');
    $response->assertJsonMissingPath('my_queue');
    $response->assertJsonMissingPath('by_priority');
    $response->assertJsonMissingPath('by_agent');
    $response->assertJsonMissingPath('daily');

    // JSON contains no sla or assigned_agent keys anywhere
    $jsonString = $response->getContent();
    expect($jsonString)->not->toContain('sla');
    expect($jsonString)->not->toContain('assigned_agent');
});

describe('counts match ticket list on seeded demo data', function () {
    dataset('roles', ['admin', 'agent']);

    test('tile counts match ticket list meta.total', function (string $role) {
        $this->seed(DatabaseSeeder::class);

        $user = $role === 'admin'
            ? User::where('email', 'admin@techzu-sla.test')->firstOrFail()
            : User::where('email', 'agent1@techzu-sla.test')->firstOrFail();

        $token = $user->createToken('test')->plainTextToken;

        $dashboardResponse = $this->withToken($token)->getJson('/api/v1/dashboard');
        $dashboardResponse->assertOk();
        $dashboard = $dashboardResponse->json();

        $checks = [
            [$dashboard['team']['open'], '/api/v1/tickets?view=active'],
            [$dashboard['team']['unassigned'], '/api/v1/tickets?assigned=unassigned'],
            [$dashboard['team']['critical'], '/api/v1/tickets?priority=critical'],
            [$dashboard['team']['sla_breached'], '/api/v1/tickets?sla_status=breached'],
            [$dashboard['team']['sla_approaching'], '/api/v1/tickets?sla_status=approaching'],
            [$dashboard['team']['sla_within'], '/api/v1/tickets?sla_status=within'],
        ];

        if ($role === 'agent') {
            $checks[] = [$dashboard['my_queue']['open'], '/api/v1/tickets?assigned=me'];
            $checks[] = [$dashboard['my_queue']['sla_approaching'], '/api/v1/tickets?assigned=me&sla_status=approaching'];
            $checks[] = [$dashboard['my_queue']['sla_breached'], '/api/v1/tickets?assigned=me&sla_status=breached'];
        } else {
            expect($dashboard)->not->toHaveKey('my_queue');
        }

        foreach ($dashboard['by_priority'] as $p) {
            $checks[] = [$p['count'], "/api/v1/tickets?priority={$p['priority']}"];
        }

        expect($dashboard['team']['sla_within'])->toBeGreaterThan(0);

        if ($role === 'admin') {
            expect($dashboard['by_agent'])->not->toBeEmpty();
            expect(collect($dashboard['by_agent'])->sum('open'))->toBeGreaterThan(0);

            foreach ($dashboard['by_agent'] as $agentRow) {
                $checks[] = [$agentRow['open'], "/api/v1/tickets?assigned={$agentRow['id']}"];
                foreach (['within', 'approaching', 'breached'] as $part) {
                    $checks[] = [$agentRow["sla_{$part}"], "/api/v1/tickets?assigned={$agentRow['id']}&sla_status={$part}"];
                }
            }
        }

        foreach ($checks as [$expectedCount, $url]) {
            $listResponse = $this->withToken($token)->getJson($url);
            $listResponse->assertOk();
            expect($listResponse->json('meta.total'))->toBe($expectedCount);
        }
    })->with('roles');
});

describe('constant query counts', function () {
    test('admin dashboard runs in 5 queries on small dataset', function () {
        $service = app(DashboardService::class);
        $now = CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC');

        $admin = User::factory()->admin()->create();
        $agents = User::factory()->agent()->count(2)->create();
        $customer = User::factory()->customer()->create();

        foreach ($agents as $agent) {
            Ticket::factory()->open()->create([
                'category_id' => $this->category->id,
                'assigned_agent_id' => $agent->id,
                'customer_id' => $customer->id,
            ]);
        }
        Ticket::factory()->resolved()->count(3)->create([
            'category_id' => $this->category->id,
            'customer_id' => $customer->id,
        ]);

        $this->expectsDatabaseQueryCount(5);
        $service->team($now);
        $service->byAgent($now);
    });

    test('admin dashboard runs in 5 queries on large dataset', function () {
        $service = app(DashboardService::class);
        $now = CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC');

        $admin = User::factory()->admin()->create();
        $agents = User::factory()->agent()->count(8)->create();
        $customer = User::factory()->customer()->create();

        for ($i = 0; $i < 200; $i++) {
            $assignedAgent = $agents[$i % 8];
            Ticket::factory()->open()->create([
                'category_id' => $this->category->id,
                'assigned_agent_id' => $assignedAgent->id,
                'customer_id' => $customer->id,
            ]);
        }

        // Finished tickets on every day of the daily window, met and missed
        for ($day = 0; $day < 14; $day++) {
            $finishedAt = $now->subDays($day)->subHour();
            Ticket::factory()->resolved()->create([
                'category_id' => $this->category->id,
                'customer_id' => $customer->id,
                'created_at' => $finishedAt->subHours(5),
                'resolved_at' => $finishedAt,
                'sla_due_at' => $day % 2 === 0 ? $finishedAt->addHour() : $finishedAt->subHour(),
            ]);
            Ticket::factory()->closed()->create([
                'category_id' => $this->category->id,
                'customer_id' => $customer->id,
                'created_at' => $finishedAt->subHours(5),
                'resolved_at' => null,
                'closed_at' => $finishedAt,
            ]);
        }

        $this->expectsDatabaseQueryCount(5);
        $service->team($now);
        $service->byAgent($now);
    });

    test('customer dashboard runs in 2 queries on small dataset', function () {
        $service = app(DashboardService::class);

        $customer = User::factory()->customer()->create();
        Ticket::factory()->open()->count(5)->create([
            'category_id' => $this->category->id,
            'customer_id' => $customer->id,
        ]);

        $this->expectsDatabaseQueryCount(2);
        $service->customerSummary($customer);
    });

    test('customer dashboard runs in 2 queries on large dataset', function () {
        $service = app(DashboardService::class);

        $customer = User::factory()->customer()->create();
        Ticket::factory()->open()->count(200)->create([
            'category_id' => $this->category->id,
            'customer_id' => $customer->id,
        ]);

        $this->expectsDatabaseQueryCount(2);
        $service->customerSummary($customer);
    });
});

describe('dashboard caching and freshness', function () {
    test('cached numbers within 60 seconds and refreshed after 60 seconds', function () {
        $start = CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC');
        $this->travelTo($start);

        $agent = User::factory()->agent()->create();
        $token = $agent->createToken('test')->plainTextToken;

        Ticket::factory()->open()->create([
            'category_id' => $this->category->id,
        ]);

        $r1 = $this->withToken($token)->getJson('/api/v1/dashboard');
        $r1->assertOk();
        expect($r1->json('team.open'))->toBe(1)
            ->and($r1->json('daily.13.created'))->toBe(1)
            ->and($r1->json('generated_at'))->toBe($start->toIso8601String());

        // 20s later: ticket created
        $this->travelTo($start->addSeconds(20));
        Ticket::factory()->open()->create([
            'category_id' => $this->category->id,
        ]);

        // 30s later: reload dashboard (cached)
        $this->travelTo($start->addSeconds(30));
        $r2 = $this->withToken($token)->getJson('/api/v1/dashboard');
        $r2->assertOk();
        expect($r2->json('team.open'))->toBe(1)
            ->and($r2->json('daily.13.created'))->toBe(1)
            ->and($r2->json('generated_at'))->toBe($start->toIso8601String());

        // 65s later: reload dashboard (cache expired)
        $this->travelTo($start->addSeconds(65));
        $r3 = $this->withToken($token)->getJson('/api/v1/dashboard');
        $r3->assertOk();
        expect($r3->json('team.open'))->toBe(2)
            ->and($r3->json('daily.13.created'))->toBe(2)
            ->and($r3->json('generated_at'))->toBe($start->addSeconds(65)->toIso8601String());
    });

    test('no cache leaking between agents for my_queue', function () {
        $start = CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC');
        $this->travelTo($start);

        $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
        $mina = User::factory()->agent()->create(['name' => 'Mina']);

        Ticket::factory()->open()->count(3)->create([
            'category_id' => $this->category->id,
            'assigned_agent_id' => $rafi->id,
        ]);
        Ticket::factory()->open()->count(2)->create([
            'category_id' => $this->category->id,
            'assigned_agent_id' => $mina->id,
        ]);

        $r1 = $this->withToken($rafi->createToken('test')->plainTextToken)->getJson('/api/v1/dashboard');
        expect($r1->json('my_queue.open'))->toBe(3);

        app('auth')->forgetGuards();

        $this->travelTo($start->addSeconds(10));
        $r2 = $this->withToken($mina->createToken('test')->plainTextToken)->getJson('/api/v1/dashboard');
        expect($r2->json('my_queue.open'))->toBe(2);
    });

    test('no cache leaking between customers for summary', function () {
        $start = CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC');
        $this->travelTo($start);

        $ana = User::factory()->customer()->create(['name' => 'Ana']);
        $ben = User::factory()->customer()->create(['name' => 'Ben']);

        Ticket::factory()->open()->count(2)->create([
            'category_id' => $this->category->id,
            'customer_id' => $ana->id,
        ]);
        Ticket::factory()->open()->count(4)->create([
            'category_id' => $this->category->id,
            'customer_id' => $ben->id,
        ]);

        $r1 = $this->withToken($ana->createToken('test')->plainTextToken)->getJson('/api/v1/dashboard');
        expect($r1->json('summary.open'))->toBe(2);

        app('auth')->forgetGuards();

        $this->travelTo($start->addSeconds(10));
        $r2 = $this->withToken($ben->createToken('test')->plainTextToken)->getJson('/api/v1/dashboard');
        expect($r2->json('summary.open'))->toBe(4);
    });

    test('generated_at is the oldest block time when my_queue is fresher than team', function () {
        $start = CarbonImmutable::parse('2026-11-05 10:00:00', 'UTC');
        $this->travelTo($start);

        $agent1 = User::factory()->agent()->create();
        $agent2 = User::factory()->agent()->create();

        // Agent 1 loads at 10:00:00 -> populates team cache at 10:00:00
        $this->withToken($agent1->createToken('test')->plainTextToken)->getJson('/api/v1/dashboard');

        // Agent 2 loads for the first time at 10:00:20
        // Team is served from cache computed at 10:00:00
        // MyQueue is computed fresh at 10:00:20
        // Oldest block is team computed_at (10:00:00)
        $this->travelTo($start->addSeconds(20));
        $r2 = $this->withToken($agent2->createToken('test')->plainTextToken)->getJson('/api/v1/dashboard');

        expect($r2->json('generated_at'))->toBe($start->toIso8601String());
    });
});
