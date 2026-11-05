<?php

use App\Data\TicketFilterData;
use App\Enums\SlaStatus;
use App\Enums\TicketPriority;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Search\TicketQueryBuilder;

test("a Customer's DTO with slaStatus, assigned and customerId set returns the same rows as without them", function () {
    $customer = User::factory()->customer()->create();
    $otherCustomer = User::factory()->customer()->create();
    $category = Category::factory()->create();

    $t1 = Ticket::factory()->open()->create([
        'customer_id' => $customer->id,
        'category_id' => $category->id,
    ]);

    Ticket::factory()->open()->create([
        'customer_id' => $otherCustomer->id,
        'category_id' => $category->id,
    ]);

    $builder = new TicketQueryBuilder();

    $cleanDto = new TicketFilterData(view: 'active');
    $filteredDto = new TicketFilterData(
        view: 'active',
        assigned: 'me',
        customerId: $otherCustomer->id,
        slaStatus: SlaStatus::Approaching,
    );

    $cleanResult = $builder->query($customer, $cleanDto)->pluck('tickets.id')->all();
    $filteredResult = $builder->query($customer, $filteredDto)->pluck('tickets.id')->all();

    expect($filteredResult)->toBe($cleanResult)
        ->and($filteredResult)->toBe([$t1->id]);
});

test("an Agent's DTO with an agent id in assigned returns the same rows as without it", function () {
    $agent = User::factory()->agent()->create();
    $rafi = User::factory()->agent()->create();
    $category = Category::factory()->create();

    $t1 = Ticket::factory()->open()->create([
        'category_id' => $category->id,
        'assigned_agent_id' => $rafi->id,
    ]);
    $t2 = Ticket::factory()->open()->create([
        'category_id' => $category->id,
        'assigned_agent_id' => null,
    ]);

    $builder = new TicketQueryBuilder();

    $cleanDto = new TicketFilterData(view: 'active');
    $filteredDto = new TicketFilterData(view: 'active', assigned: (string) $rafi->id);

    $cleanResult = $builder->query($agent, $cleanDto)->pluck('tickets.id')->all();
    $filteredResult = $builder->query($agent, $filteredDto)->pluck('tickets.id')->all();

    expect($filteredResult)->toBe($cleanResult)
        ->and($filteredResult)->toContain($t1->id, $t2->id);
});

test("an Admin's DTO with Rafi's id returns only Rafi's tickets", function () {
    $admin = User::factory()->admin()->create();
    $rafi = User::factory()->agent()->create();
    $mina = User::factory()->agent()->create();
    $category = Category::factory()->create();

    $t1 = Ticket::factory()->open()->create([
        'category_id' => $category->id,
        'assigned_agent_id' => $rafi->id,
    ]);
    $t2 = Ticket::factory()->open()->create([
        'category_id' => $category->id,
        'assigned_agent_id' => $mina->id,
    ]);

    $builder = new TicketQueryBuilder();
    $dto = new TicketFilterData(view: 'active', assigned: (string) $rafi->id);

    $results = $builder->query($admin, $dto)->pluck('tickets.id')->all();

    expect($results)->toBe([$t1->id]);
});

test("a Customer's query never returns another customer's ticket whatever the filters", function () {
    $customer = User::factory()->customer()->create();
    $otherCustomer = User::factory()->customer()->create();
    $category = Category::factory()->create();

    $myTicket = Ticket::factory()->open()->create([
        'customer_id' => $customer->id,
        'category_id' => $category->id,
    ]);
    Ticket::factory()->open()->create([
        'customer_id' => $otherCustomer->id,
        'category_id' => $category->id,
    ]);

    $builder = new TicketQueryBuilder();
    $dto = new TicketFilterData(
        view: 'all',
        customerId: $otherCustomer->id,
    );

    $results = $builder->query($customer, $dto)->pluck('tickets.id')->all();

    expect($results)->toBe([$myTicket->id]);
});




test('date boundaries in builder are computed in config tickets.timezone', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();

    // In Asia/Dhaka (+06:00):
    // 2026-11-02 00:00:00 +06:00 = 2026-11-01 18:00:00 UTC
    // 2026-11-02 23:59:59 +06:00 = 2026-11-02 17:59:59 UTC
    // Next day start is 2026-11-02 18:00:00 UTC

    $justBefore = Ticket::factory()->open()->create([
        'category_id' => $category->id,
        'created_at' => \Carbon\CarbonImmutable::parse('2026-11-01 17:59:59', 'UTC'),
    ]);
    $exactStart = Ticket::factory()->open()->create([
        'category_id' => $category->id,
        'created_at' => \Carbon\CarbonImmutable::parse('2026-11-01 18:00:00', 'UTC'),
    ]);
    $exactEnd = Ticket::factory()->open()->create([
        'category_id' => $category->id,
        'created_at' => \Carbon\CarbonImmutable::parse('2026-11-02 17:59:59', 'UTC'),
    ]);
    $justAfter = Ticket::factory()->open()->create([
        'category_id' => $category->id,
        'created_at' => \Carbon\CarbonImmutable::parse('2026-11-02 18:00:00', 'UTC'),
    ]);

    $builder = new TicketQueryBuilder();
    $dto = new TicketFilterData(
        view: 'all',
        createdFrom: '2026-11-02',
        createdTo: '2026-11-02',
    );

    $results = $builder->query($admin, $dto)->pluck('tickets.id')->all();

    expect($results)->toContain($exactStart->id, $exactEnd->id)
        ->and($results)->not->toContain($justBefore->id)
        ->and($results)->not->toContain($justAfter->id);
});

test('25 tickets with the same created_at give two pages with 25 distinct ids for every sort value', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();

    $fixedTime = \Carbon\CarbonImmutable::parse('2026-11-02 12:00:00', 'UTC');

    for ($i = 0; $i < 25; $i++) {
        Ticket::factory()->open()->create([
            'category_id' => $category->id,
            'created_at' => $fixedTime,
            'updated_at' => $fixedTime,
            'priority' => TicketPriority::Medium,
            'sla_due_at' => $fixedTime->addDays(2),
        ]);
    }

    $builder = new TicketQueryBuilder();
    $sortOptions = [
        null,
        'created_at',
        '-created_at',
        'updated_at',
        '-updated_at',
        'priority',
        '-priority',
        'sla_due_at',
        '-sla_due_at',
    ];

    foreach ($sortOptions as $sort) {
        $dto = new TicketFilterData(view: 'all', sort: $sort);

        // Page 1 with perPage = 20
        Illuminate\Pagination\Paginator::currentPageResolver(fn () => 1);
        $page1 = $builder->paginate($admin, $dto, 20);

        // Page 2 with perPage = 20
        Illuminate\Pagination\Paginator::currentPageResolver(fn () => 2);
        $page2 = $builder->paginate($admin, $dto, 20);

        $ids1 = collect($page1->items())->pluck('id')->all();
        $ids2 = collect($page2->items())->pluck('id')->all();

        expect($ids1)->toHaveCount(20)
            ->and($ids2)->toHaveCount(5);

        $allIds = array_merge($ids1, $ids2);
        expect(array_unique($allIds))->toHaveCount(25);
    }
});
