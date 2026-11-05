<?php

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;

beforeEach(function () {
    \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS = 0');
    \App\Models\Ticket::query()->delete();
    \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS = 1');

    $this->agent = User::factory()->agent()->create();
    $this->agentToken = $this->agent->createToken('agent')->plainTextToken;
    $this->category = Category::factory()->create();
});

test('keyword search with print lists only the printer ticket', function () {
    $printer = Ticket::factory()->open()->create([
        'subject' => 'Printer offline',
        'category_id' => $this->category->id,
    ]);

    Ticket::factory()->open()->create([
        'subject' => 'Email bounce',
        'category_id' => $this->category->id,
    ]);

    $response = $this->withToken($this->agentToken)
        ->getJson('/api/v1/tickets?search=print');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$printer->id]);
});

test('keyword search with all words required lists only ticket matching all words', function () {
    $t1 = Ticket::factory()->open()->create([
        'subject' => 'Printer offline',
        'category_id' => $this->category->id,
    ]);
    Ticket::factory()->open()->create([
        'subject' => 'Printer jammed',
        'category_id' => $this->category->id,
    ]);
    Ticket::factory()->open()->create([
        'subject' => 'Office offline',
        'category_id' => $this->category->id,
    ]);

    $response = $this->withToken($this->agentToken)
        ->getJson('/api/v1/tickets?search=printer+offline');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$t1->id]);
});

test('keyword search matches ticket description', function () {
    $t1 = Ticket::factory()->open()->create([
        'subject' => 'Help',
        'description' => 'The invoice total is wrong',
        'category_id' => $this->category->id,
    ]);
    Ticket::factory()->open()->create([
        'subject' => 'Other issue',
        'description' => 'Something else entirely',
        'category_id' => $this->category->id,
    ]);

    $response = $this->withToken($this->agentToken)
        ->getJson('/api/v1/tickets?search=invoice');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$t1->id]);
});

test('keyword search does not match text inside a word', function () {
    Ticket::factory()->open()->create([
        'subject' => 'Printer offline',
        'category_id' => $this->category->id,
    ]);

    $response = $this->withToken($this->agentToken)
        ->getJson('/api/v1/tickets?search=rint');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([]);
});

test('search operators in keyword search are treated as plain text', function () {
    $t1 = Ticket::factory()->open()->create([
        'subject' => 'Printer offline',
        'category_id' => $this->category->id,
    ]);

    $response = $this->withToken($this->agentToken)
        ->getJson('/api/v1/tickets?search=+printer+-offline*');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$t1->id]);
});

test('common words like about are found because stopwords are disabled on the index', function () {
    $t1 = Ticket::factory()->open()->create([
        'subject' => 'Question about invoice',
        'category_id' => $this->category->id,
    ]);

    $response = $this->withToken($this->agentToken)
        ->getJson('/api/v1/tickets?search=about');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$t1->id]);
});

test('search validation rejects words under 3 characters and strings over 100 characters', function () {
    $this->withToken($this->agentToken)
        ->getJson('/api/v1/tickets?search=ab')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['search' => 'Search for words of at least 3 characters.']);

    $tooLong = str_repeat('a', 101);
    $this->withToken($this->agentToken)
        ->getJson("/api/v1/tickets?search={$tooLong}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['search']);
});

test('customer searching keyword sees only their own tickets', function () {
    $ana = User::factory()->customer()->create();
    $anaToken = $ana->createToken('ana')->plainTextToken;
    $ben = User::factory()->customer()->create();

    $anaTicket = Ticket::factory()->open()->create([
        'subject' => 'Printer offline',
        'customer_id' => $ana->id,
        'category_id' => $this->category->id,
    ]);
    Ticket::factory()->open()->create([
        'subject' => 'Printer offline',
        'customer_id' => $ben->id,
        'category_id' => $this->category->id,
    ]);

    $response = $this->withToken($anaToken)
        ->getJson('/api/v1/tickets?search=printer');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$anaTicket->id]);
});

test('search by exact ticket number case-insensitive lists only that ticket', function () {
    $t1 = Ticket::factory()->open()->create([
        'ticket_number' => 'TKT-2026-000123',
        'category_id' => $this->category->id,
    ]);
    Ticket::factory()->open()->create([
        'ticket_number' => 'TKT-2026-001230',
        'category_id' => $this->category->id,
    ]);

    $response = $this->withToken($this->agentToken)
        ->getJson('/api/v1/tickets?search=tkt-2026-000123');

    $response->assertOk();
    $numbers = collect($response->json('data'))->pluck('ticket_number')->all();
    expect($numbers)->toBe(['TKT-2026-000123']);
});

test('search by exact ticket number ignores view tab', function () {
    $resolved = Ticket::factory()->resolved()->create([
        'ticket_number' => 'TKT-2026-000123',
        'category_id' => $this->category->id,
    ]);

    $response = $this->withToken($this->agentToken)
        ->getJson('/api/v1/tickets?view=active&search=TKT-2026-000123');

    $response->assertOk();
    $numbers = collect($response->json('data'))->pluck('ticket_number')->all();
    expect($numbers)->toBe(['TKT-2026-000123']);
});

test('customer searching another customer ticket number gets empty list', function () {
    $ana = User::factory()->customer()->create();
    $anaToken = $ana->createToken('ana')->plainTextToken;
    $ben = User::factory()->customer()->create();

    Ticket::factory()->open()->create([
        'ticket_number' => 'TKT-2026-000123',
        'customer_id' => $ben->id,
        'category_id' => $this->category->id,
    ]);

    $response = $this->withToken($anaToken)
        ->getJson('/api/v1/tickets?search=TKT-2026-000123');

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

test('every filter at once lists only the matching ticket among decoys', function () {
    $admin = User::factory()->admin()->create();
    $adminToken = $admin->createToken('admin')->plainTextToken;

    $ana = User::factory()->customer()->create(['name' => 'Ana']);
    $ben = User::factory()->customer()->create(['name' => 'Ben']);
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $mina = User::factory()->agent()->create(['name' => 'Mina']);
    $billing = Category::factory()->create(['name' => 'Billing']);
    $technical = Category::factory()->create(['name' => 'Technical']);

    $tz = config('tickets.timezone', 'Asia/Dhaka');
    $targetDate = \Carbon\CarbonImmutable::parse('2026-11-02 12:00:00', $tz)->utc();
    $decoyDate = \Carbon\CarbonImmutable::parse('2026-11-05 12:00:00', $tz)->utc();

    $target = Ticket::factory()->open()->create([
        'subject' => 'Invoice wrong',
        'customer_id' => $ana->id,
        'priority' => \App\Enums\TicketPriority::High,
        'category_id' => $billing->id,
        'assigned_agent_id' => $rafi->id,
        'created_at' => $targetDate,
        'sla_warning_at' => now()->subHour(),
        'sla_due_at' => now()->addHour(),
    ]);

    // Decoy 1: different keyword
    Ticket::factory()->open()->create([
        'subject' => 'Printer offline',
        'customer_id' => $ana->id,
        'priority' => \App\Enums\TicketPriority::High,
        'category_id' => $billing->id,
        'assigned_agent_id' => $rafi->id,
        'created_at' => $targetDate,
        'sla_warning_at' => now()->subHour(),
        'sla_due_at' => now()->addHour(),
    ]);

    // Decoy 2: different priority
    Ticket::factory()->open()->create([
        'subject' => 'Invoice wrong',
        'customer_id' => $ana->id,
        'priority' => \App\Enums\TicketPriority::Medium,
        'category_id' => $billing->id,
        'assigned_agent_id' => $rafi->id,
        'created_at' => $targetDate,
        'sla_warning_at' => now()->subHour(),
        'sla_due_at' => now()->addHour(),
    ]);

    // Decoy 3: different category
    Ticket::factory()->open()->create([
        'subject' => 'Invoice wrong',
        'customer_id' => $ana->id,
        'priority' => \App\Enums\TicketPriority::High,
        'category_id' => $technical->id,
        'assigned_agent_id' => $rafi->id,
        'created_at' => $targetDate,
        'sla_warning_at' => now()->subHour(),
        'sla_due_at' => now()->addHour(),
    ]);

    // Decoy 4: different assigned agent
    Ticket::factory()->open()->create([
        'subject' => 'Invoice wrong',
        'customer_id' => $ana->id,
        'priority' => \App\Enums\TicketPriority::High,
        'category_id' => $billing->id,
        'assigned_agent_id' => $mina->id,
        'created_at' => $targetDate,
        'sla_warning_at' => now()->subHour(),
        'sla_due_at' => now()->addHour(),
    ]);

    // Decoy 5: different customer
    Ticket::factory()->open()->create([
        'subject' => 'Invoice wrong',
        'customer_id' => $ben->id,
        'priority' => \App\Enums\TicketPriority::High,
        'category_id' => $billing->id,
        'assigned_agent_id' => $rafi->id,
        'created_at' => $targetDate,
        'sla_warning_at' => now()->subHour(),
        'sla_due_at' => now()->addHour(),
    ]);

    // Decoy 6: different date
    Ticket::factory()->open()->create([
        'subject' => 'Invoice wrong',
        'customer_id' => $ana->id,
        'priority' => \App\Enums\TicketPriority::High,
        'category_id' => $billing->id,
        'assigned_agent_id' => $rafi->id,
        'created_at' => $decoyDate,
        'sla_warning_at' => now()->subHour(),
        'sla_due_at' => now()->addHour(),
    ]);

    // Decoy 7: different SLA status (within deadline)
    Ticket::factory()->open()->create([
        'subject' => 'Invoice wrong',
        'customer_id' => $ana->id,
        'priority' => \App\Enums\TicketPriority::High,
        'category_id' => $billing->id,
        'assigned_agent_id' => $rafi->id,
        'created_at' => $targetDate,
        'sla_warning_at' => now()->addHour(),
        'sla_due_at' => now()->addHours(2),
    ]);

    $url = '/api/v1/tickets?' . http_build_query([
        'search' => 'invoice',
        'priority' => 'high',
        'category_id' => $billing->id,
        'assigned' => (string) $rafi->id,
        'customer_id' => $ana->id,
        'created_from' => '2026-11-02',
        'created_to' => '2026-11-02',
        'sla_status' => 'approaching',
    ]);

    $response = $this->withToken($adminToken)->getJson($url);
    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$target->id]);
});
