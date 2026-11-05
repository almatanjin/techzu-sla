<?php

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;

beforeEach(function () {
    $this->ana = User::factory()->customer()->create(['name' => 'Ana']);
    $this->anaToken = $this->ana->createToken('ana')->plainTextToken;

    $this->rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $this->rafiToken = $this->rafi->createToken('rafi')->plainTextToken;

    $this->ben = User::factory()->customer()->create(['name' => 'Ben']);
    $this->benToken = $this->ben->createToken('ben')->plainTextToken;

    $this->category = Category::factory()->create(['name' => 'Billing']);
});

it('allows customer Ana to post a reply on her open ticket and retrieve it', function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->ana->id,
        'category_id' => $this->category->id,
    ]);

    $postResponse = $this->withToken($this->anaToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'body' => 'The invoice still shows the old address.',
        ]);

    $postResponse->assertStatus(201)
        ->assertJsonPath('type', 'reply')
        ->assertJsonPath('body', 'The invoice still shows the old address.')
        ->assertJsonPath('author.id', $this->ana->id)
        ->assertJsonPath('author.name', 'Ana')
        ->assertJsonPath('author.role', 'customer');

    $messageId = $postResponse->json('id');

    $getResponse = $this->withToken($this->anaToken)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/messages");

    $getResponse->assertOk()
        ->assertJsonPath('data.0.id', $messageId)
        ->assertJsonPath('data.0.type', 'reply')
        ->assertJsonPath('data.0.body', 'The invoice still shows the old address.')
        ->assertJsonPath('data.0.author.id', $this->ana->id)
        ->assertJsonPath('data.0.author.name', 'Ana');
});

it("sets author Rafi when agent Rafi posts a reply", function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->ana->id,
        'category_id' => $this->category->id,
    ]);

    $response = $this->withToken($this->rafiToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'body' => 'I have updated the invoice address for you.',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('type', 'reply')
        ->assertJsonPath('author.id', $this->rafi->id)
        ->assertJsonPath('author.name', 'Rafi')
        ->assertJsonPath('author.role', 'agent');
});

it("answers 404 and stores nothing when customer Ben posts on Ana's ticket", function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->ana->id,
        'category_id' => $this->category->id,
    ]);

    $response = $this->withToken($this->benToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'body' => 'Can I see this ticket?',
        ]);

    $response->assertNotFound();
    expect(\App\Models\TicketMessage::count())->toBe(0);
});

it("allows agent Rafi to post an internal note with 201", function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->ana->id,
        'category_id' => $this->category->id,
    ]);

    $response = $this->withToken($this->rafiToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'type' => 'internal_note',
            'body' => 'Customer is on the legacy plan.',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('type', 'internal_note')
        ->assertJsonPath('body', 'Customer is on the legacy plan.')
        ->assertJsonPath('author.id', $this->rafi->id);
});

it("answers 403 and stores nothing when Ana tries to post an internal note", function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->ana->id,
        'category_id' => $this->category->id,
    ]);

    $response = $this->withToken($this->anaToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'type' => 'internal_note',
            'body' => 'hello',
        ]);

    $response->assertForbidden();
    expect(\App\Models\TicketMessage::count())->toBe(0);
});

it("validates body and type when posting a message", function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->ana->id,
        'category_id' => $this->category->id,
    ]);

    // Body of only spaces answers 422 on body
    $response = $this->withToken($this->anaToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'body' => '   ',
        ]);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['body']);

    // Body of 10,001 characters answers 422 on body
    $response = $this->withToken($this->anaToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'body' => str_repeat('a', 10001),
        ]);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['body']);

    // Type memo answers 422 on type
    $response = $this->withToken($this->rafiToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'type' => 'memo',
            'body' => 'x',
        ]);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['type']);
});

it("rejects replies on closed tickets with 403, but accepts replies on resolved tickets with 201", function () {
    $closedTicket = Ticket::factory()->closed()->create([
        'customer_id' => $this->ana->id,
        'category_id' => $this->category->id,
    ]);

    // Ana on closed ticket answers 403
    $this->withToken($this->anaToken)
        ->postJson("/api/v1/tickets/{$closedTicket->ticket_number}/messages", [
            'body' => 'Can you help reopen?',
        ])->assertForbidden();

    // Agent on closed ticket answers 403
    $this->withToken($this->rafiToken)
        ->postJson("/api/v1/tickets/{$closedTicket->ticket_number}/messages", [
            'body' => 'Reopening note',
        ])->assertForbidden();

    // Resolved ticket accepts reply with 201
    $resolvedTicket = Ticket::factory()->resolved()->create([
        'customer_id' => $this->ana->id,
        'category_id' => $this->category->id,
    ]);

    $this->withToken($this->anaToken)
        ->postJson("/api/v1/tickets/{$resolvedTicket->ticket_number}/messages", [
            'body' => 'Thank you for resolving this.',
        ])->assertStatus(201);
});

it("leaves status and lock_version unchanged after posting a message", function () {
    $ticket = Ticket::factory()->waitingForCustomer()->create([
        'customer_id' => $this->ana->id,
        'category_id' => $this->category->id,
        'lock_version' => 4,
    ]);

    $this->withToken($this->anaToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'body' => 'Here is the requested information.',
        ])->assertStatus(201);

    $ticket->refresh();
    expect($ticket->status->value)->toBe('waiting_for_customer')
        ->and($ticket->lock_version)->toBe(4);
});

it("answers 405 Method Not Allowed on PATCH, PUT, and DELETE to messages URL", function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->ana->id,
        'category_id' => $this->category->id,
    ]);

    $this->withToken($this->anaToken)
        ->patchJson("/api/v1/tickets/{$ticket->ticket_number}/messages", ['body' => 'update'])
        ->assertStatus(405);

    $this->withToken($this->anaToken)
        ->putJson("/api/v1/tickets/{$ticket->ticket_number}/messages", ['body' => 'update'])
        ->assertStatus(405);

    $this->withToken($this->anaToken)
        ->deleteJson("/api/v1/tickets/{$ticket->ticket_number}/messages")
        ->assertStatus(405);
});

it("records activity entries for replies and internal notes, and omits notes for customers", function () {
    $ticket = Ticket::factory()->open()->create([
        'customer_id' => $this->ana->id,
        'category_id' => $this->category->id,
    ]);

    // Ana replies
    $replyResponse = $this->withToken($this->anaToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'body' => 'Customer reply here.',
        ]);
    $replyResponse->assertStatus(201);
    $replyId = $replyResponse->json('id');

    // Rafi adds internal note
    $this->app['auth']->forgetGuards();
    $noteResponse = $this->withToken($this->rafiToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'type' => 'internal_note',
            'body' => 'Internal note here.',
        ]);
    $noteResponse->assertStatus(201);
    $noteId = $noteResponse->json('id');

    // Agent reads activities: sees both replied and internal_note_added
    $this->app['auth']->forgetGuards();
    $agentActivities = $this->withToken($this->rafiToken)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities")
        ->assertOk()
        ->json();

    $repliedActivity = collect($agentActivities)->firstWhere('type', 'replied');
    expect($repliedActivity)->not->toBeNull()
        ->and($repliedActivity['actor']['id'])->toBe($this->ana->id)
        ->and($repliedActivity['new_values'])->toBe([
            'message_id' => $replyId,
            'attachment_count' => 0,
        ]);

    $noteActivity = collect($agentActivities)->firstWhere('type', 'internal_note_added');
    expect($noteActivity)->not->toBeNull()
        ->and($noteActivity['actor']['id'])->toBe($this->rafi->id)
        ->and($noteActivity['new_values'])->toBe([
            'message_id' => $noteId,
            'attachment_count' => 0,
        ]);

    // Ana (customer) reads activities: sees replied, omits internal_note_added
    $this->app['auth']->forgetGuards();
    $customerActivities = $this->withToken($this->anaToken)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}/activities")
        ->assertOk()
        ->json();

    expect(collect($customerActivities)->contains('type', 'replied'))->toBeTrue()
        ->and(collect($customerActivities)->contains('type', 'internal_note_added'))->toBeFalse();
});
