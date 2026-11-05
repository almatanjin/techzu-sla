<?php

use App\Data\TicketFilterData;
use App\Enums\SlaStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Search\TicketQueryBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Ticket::query()->delete();
});

function seedTicketsForQueryPlans(): array
{
    $admin = User::factory()->admin()->create();
    $agent = User::factory()->agent()->create();
    $customer = User::factory()->customer()->create();

    $customers = [$customer];
    for ($c = 0; $c < 49; $c++) {
        $customers[] = User::factory()->customer()->create();
    }

    $cat1 = Category::factory()->create();
    $cat2 = Category::factory()->create();

    $now = CarbonImmutable::now();
    $statuses = ['open', 'in_progress', 'waiting_for_customer', 'resolved', 'closed'];
    $priorities = ['low', 'medium', 'high', 'critical'];

    $rows = [];
    for ($i = 1; $i <= 3000; $i++) {
        $status = $statuses[$i % count($statuses)];
        $priority = $priorities[$i % count($priorities)];
        $cat = ($i % 2 === 0) ? $cat1 : $cat2;
        $cust = $customers[$i % count($customers)];
        $isPrinter = ($i % 15 === 0);

        $rows[] = [
            'ticket_number' => sprintf('TKT-2026-%06d', $i),
            'subject' => $isPrinter ? "Printer failure {$i}" : "General ticket {$i}",
            'description' => $isPrinter ? "Printer is broken and needs replacement {$i}" : "Standard details {$i}",
            'priority' => $priority,
            'status' => $status,
            'category_id' => $cat->id,
            'customer_id' => $cust->id,
            'created_by_id' => $cust->id,
            'assigned_agent_id' => ($i % 4 === 0) ? $agent->id : null,
            'sla_policy_minutes' => 120,
            'sla_started_at' => $now->subDays(10),
            'sla_warning_at' => $now->subDays(10)->addMinutes(60),
            'sla_due_at' => $now->addMinutes($i * 10),
            'created_at' => $now->subMinutes($i * 5),
            'updated_at' => $now->subMinutes($i * 5),
        ];
    }

    foreach (array_chunk($rows, 1000) as $chunk) {
        DB::table('tickets')->insert($chunk);
    }

    DB::statement('ANALYZE TABLE tickets');

    return compact('admin', 'agent', 'customer', 'cat1', 'cat2');
}

test('keyword search EXPLAIN query plan uses the FULLTEXT index', function () {
    $seeded = seedTicketsForQueryPlans();
    $agent = $seeded['agent'];

    $builder = new TicketQueryBuilder();
    $dto = new TicketFilterData(view: 'active', search: 'printer broken');
    $query = $builder->query($agent, $dto)->limit(20);

    $explain = DB::select('EXPLAIN FORMAT=TRADITIONAL ' . $query->toSql(), $query->getBindings());

    expect($explain)->not->toBeEmpty();
    $plan = $explain[0];
    expect($plan->key)->toBe('tickets_fulltext')
        ->and($plan->type)->toBe('fulltext');
});

test('ticket number search uses the unique ticket_number index', function () {
    $seeded = seedTicketsForQueryPlans();
    $agent = $seeded['agent'];

    $builder = new TicketQueryBuilder();
    $dto = new TicketFilterData(search: 'TKT-2026-000123');
    $query = $builder->query($agent, $dto)->limit(20);

    $explain = DB::select('EXPLAIN FORMAT=TRADITIONAL ' . $query->toSql(), $query->getBindings());

    expect($explain)->not->toBeEmpty();
    $plan = $explain[0];
    expect($plan->key)->toBe('tickets_ticket_number_unique')
        ->and($plan->type)->toBeIn(['const', 'eq_ref', 'ref']);
});

test('default Active view for an agent is not a full scan', function () {
    $seeded = seedTicketsForQueryPlans();
    $agent = $seeded['agent'];

    $builder = new TicketQueryBuilder();
    $dto = new TicketFilterData(view: 'active');
    $query = $builder->query($agent, $dto)->limit(20);

    $explain = DB::select('EXPLAIN FORMAT=TRADITIONAL ' . $query->toSql(), $query->getBindings());

    expect($explain)->not->toBeEmpty();
    $plan = $explain[0];
    expect($plan->type)->not->toBe('ALL');
});

test("customer Active list uses customer_id_status_created_at composite index", function () {
    $seeded = seedTicketsForQueryPlans();
    $customer = $seeded['customer'];

    $builder = new TicketQueryBuilder();
    $dto = new TicketFilterData(view: 'active');
    $query = $builder->query($customer, $dto)->limit(20);

    $explain = DB::select('EXPLAIN FORMAT=TRADITIONAL ' . $query->toSql(), $query->getBindings());

    expect($explain)->not->toBeEmpty();
    $plan = $explain[0];
    expect($plan->key)->toBe('tickets_customer_id_status_created_at_index')
        ->and($plan->type)->not->toBe('ALL');
});

test('category filter is not a full scan', function () {
    $seeded = seedTicketsForQueryPlans();
    $agent = $seeded['agent'];
    $cat1 = $seeded['cat1'];

    $builder = new TicketQueryBuilder();
    $dto = new TicketFilterData(view: 'active', categoryId: $cat1->id);
    $query = $builder->query($agent, $dto)->limit(20);

    $explain = DB::select('EXPLAIN FORMAT=TRADITIONAL ' . $query->toSql(), $query->getBindings());

    expect($explain)->not->toBeEmpty();
    $plan = $explain[0];
    expect($plan->type)->not->toBe('ALL');
});

test('breached and approaching SLA filters are not full scans', function () {
    $seeded = seedTicketsForQueryPlans();
    $agent = $seeded['agent'];

    $builder = new TicketQueryBuilder();

    // Approaching SLA
    $dtoApproaching = new TicketFilterData(view: 'active', slaStatus: SlaStatus::Approaching);
    $queryApproaching = $builder->query($agent, $dtoApproaching)->limit(20);
    $explainApproaching = DB::select('EXPLAIN FORMAT=TRADITIONAL ' . $queryApproaching->toSql(), $queryApproaching->getBindings());
    expect($explainApproaching)->not->toBeEmpty();
    expect($explainApproaching[0]->type)->not->toBe('ALL');

    // Breached SLA
    $dtoBreached = new TicketFilterData(view: 'active', slaStatus: SlaStatus::Breached);
    $queryBreached = $builder->query($agent, $dtoBreached)->limit(20);
    $explainBreached = DB::select('EXPLAIN FORMAT=TRADITIONAL ' . $queryBreached->toSql(), $queryBreached->getBindings());
    expect($explainBreached)->not->toBeEmpty();
    expect($explainBreached[0]->type)->not->toBe('ALL');
});
