<?php

use App\Enums\TicketMessageType;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->ana = User::factory()->customer()->create(['name' => 'Ana']);
    $this->anaToken = $this->ana->createToken('ana')->plainTextToken;

    $this->rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $this->rafiToken = $this->rafi->createToken('rafi')->plainTextToken;

    $this->ben = User::factory()->customer()->create(['name' => 'Ben']);
    $this->benToken = $this->ben->createToken('ben')->plainTextToken;

    $this->category = Category::factory()->create(['name' => 'Billing']);
});

it('paginates 35 messages newest first 30 per page with next cursor', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->ana->id,
        'category_id' => $this->category->id,
    ]);

    // Create 35 messages with incrementing times
    for ($i = 1; $i <= 35; $i++) {
        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'author_id' => $this->ana->id,
            'type' => TicketMessageType::Reply,
            'body' => "Message {$i}",
            'created_at' => now()->addMinutes($i),
        ]);
    }

    $firstPage = $this->withToken($this->anaToken)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/messages")
        ->assertOk()
        ->json();

    expect(count($firstPage['data']))->toBe(30)
        ->and($firstPage['data'][0]['body'])->toBe('Message 35')
        ->and($firstPage['data'][29]['body'])->toBe('Message 6')
        ->and($firstPage['links']['next'])->not->toBeNull();

    $nextCursor = $firstPage['meta']['next_cursor'];
    expect($nextCursor)->not->toBeNull();

    $secondPage = $this->withToken($this->anaToken)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/messages?cursor={$nextCursor}")
        ->assertOk()
        ->json();

    expect(count($secondPage['data']))->toBe(5)
        ->and($secondPage['data'][0]['body'])->toBe('Message 5')
        ->and($secondPage['data'][4]['body'])->toBe('Message 1')
        ->and($secondPage['links']['next'])->toBeNull();
});

it('hides internal notes from customer while showing all messages to agent', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->ana->id,
        'category_id' => $this->category->id,
    ]);

    TicketMessage::create([
        'ticket_id' => $ticket->id,
        'author_id' => $this->ana->id,
        'type' => TicketMessageType::Reply,
        'body' => 'Reply 1',
    ]);

    TicketMessage::create([
        'ticket_id' => $ticket->id,
        'author_id' => $this->rafi->id,
        'type' => TicketMessageType::InternalNote,
        'body' => 'Internal note',
    ]);

    TicketMessage::create([
        'ticket_id' => $ticket->id,
        'author_id' => $this->rafi->id,
        'type' => TicketMessageType::Reply,
        'body' => 'Reply 2',
    ]);

    // Ana (customer) receives only the 2 replies
    $customerMessages = $this->withToken($this->anaToken)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/messages")
        ->assertOk()
        ->json('data');

    expect(count($customerMessages))->toBe(2)
        ->and(collect($customerMessages)->pluck('type')->all())->toBe(['reply', 'reply']);

    // Agent receives all 3 messages
    $this->app['auth']->forgetGuards();
    $agentMessages = $this->withToken($this->rafiToken)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/messages")
        ->assertOk()
        ->json('data');

    expect(count($agentMessages))->toBe(3)
        ->and(collect($agentMessages)->pluck('type')->all())->toContain('internal_note');
});

it('returns 404 when Ben requests Ana messages', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->ana->id,
        'category_id' => $this->category->id,
    ]);

    $this->withToken($this->benToken)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/messages")
        ->assertNotFound();
});

it('runs the same number of queries for 3 and 30 messages with different authors', function () {
    $ticket1 = Ticket::factory()->open()->create([
        'customer_id' => $this->ana->id,
        'category_id' => $this->category->id,
    ]);

    $authors = User::factory()->count(30)->customer()->create();

    for ($i = 0; $i < 3; $i++) {
        TicketMessage::create([
            'ticket_id' => $ticket1->id,
            'author_id' => $authors[$i]->id,
            'type' => TicketMessageType::Reply,
            'body' => "Msg {$i}",
        ]);
    }

    $ticket2 = Ticket::factory()->open()->create([
        'customer_id' => $this->ana->id,
        'category_id' => $this->category->id,
    ]);

    for ($i = 0; $i < 30; $i++) {
        TicketMessage::create([
            'ticket_id' => $ticket2->id,
            'author_id' => $authors[$i]->id,
            'type' => TicketMessageType::Reply,
            'body' => "Msg {$i}",
        ]);
    }

    // Warm-up / resolve user
    $this->withToken($this->anaToken)->getJson("/api/v1/tickets/{$ticket1->ticket_number}/messages");

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->withToken($this->anaToken)->getJson("/api/v1/tickets/{$ticket1->ticket_number}/messages");
    $queryCount1 = count(DB::getQueryLog());
    DB::disableQueryLog();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->withToken($this->anaToken)->getJson("/api/v1/tickets/{$ticket2->ticket_number}/messages");
    $queryCount2 = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount1)->toBeGreaterThan(0)
        ->and($queryCount1)->toBe($queryCount2);
});
