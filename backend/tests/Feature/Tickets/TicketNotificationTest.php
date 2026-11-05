<?php

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Tickets\TicketCreatedNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->category = Category::factory()->create(['name' => 'Support', 'is_active' => true]);
});

it('sends TicketCreatedNotification to customer Ana when she creates a ticket', function () {
    Notification::fake();

    $ana = User::factory()->customer()->create(['name' => 'Ana']);
    $token = $ana->createToken('ana-token')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Cannot log in',
            'description' => 'Password reset is not working.',
            'category_id' => $this->category->id,
        ]);

    $response->assertCreated();

    Notification::assertSentTo($ana, TicketCreatedNotification::class, 1);
});

it('sends TicketAssignedNotification to Rafi on automatic assignment', function () {
    Notification::fake();

    $rafi = User::factory()->agent()->create(['name' => 'Rafi', 'is_available' => true]);
    $rafi->categories()->attach($this->category);

    $customer = User::factory()->customer()->create(['name' => 'Customer']);
    $token = $customer->createToken('cust-token')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Billing issue',
            'description' => 'Need help with invoice.',
            'category_id' => $this->category->id,
        ]);

    $response->assertCreated();

    Notification::assertSentTo($rafi, \App\Notifications\Tickets\TicketAssignedNotification::class, 1);
});

it('sends TicketNeedsAgentNotification to active admins when every agent is unavailable, and retry sends nothing more', function () {
    Notification::fake();

    $admin1 = User::factory()->admin()->create(['name' => 'Admin 1']);
    $admin2 = User::factory()->admin()->create(['name' => 'Admin 2']);
    $inactiveAdmin = User::factory()->admin()->create(['name' => 'Inactive Admin', 'is_active' => false]);

    // Agent unavailable
    $agent = User::factory()->agent()->unavailable()->create();
    $agent->categories()->attach($this->category);

    $customer = User::factory()->customer()->create(['name' => 'Customer']);
    $token = $customer->createToken('cust-token')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Unassignable ticket',
            'description' => 'Nobody can take this ticket right now.',
            'category_id' => $this->category->id,
        ]);

    $response->assertCreated();

    Notification::assertSentToTimes($admin1, \App\Notifications\Tickets\TicketNeedsAgentNotification::class, 1);
    Notification::assertSentToTimes($admin2, \App\Notifications\Tickets\TicketNeedsAgentNotification::class, 1);
    Notification::assertNotSentTo($inactiveAdmin, \App\Notifications\Tickets\TicketNeedsAgentNotification::class);

    // Running retry command sends nothing more
    $this->artisan('tickets:assign-unassigned')->assertSuccessful();

    Notification::assertSentToTimes($admin1, \App\Notifications\Tickets\TicketNeedsAgentNotification::class, 1);
    Notification::assertSentToTimes($admin2, \App\Notifications\Tickets\TicketNeedsAgentNotification::class, 1);
});

it('notifies Mina only when admin moves ticket from Rafi to Mina', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create(['name' => 'Admin']);
    $adminToken = $admin->createToken('admin')->plainTextToken;

    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $mina = User::factory()->agent()->create(['name' => 'Mina']);

    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => $rafi->id,
        'status' => TicketStatus::Open,
        'lock_version' => 0,
    ]);

    $response = $this->withToken($adminToken)
        ->putJson("/api/v1/tickets/{$ticket->ticket_number}/assignment", [
            'agent_id' => $mina->id,
            'lock_version' => 0,
        ]);

    $response->assertOk();

    Notification::assertSentToTimes($mina, \App\Notifications\Tickets\TicketAssignedNotification::class, 1);
    Notification::assertNotSentTo($rafi, \App\Notifications\Tickets\TicketAssignedNotification::class);
    Notification::assertNotSentTo($admin, \App\Notifications\Tickets\TicketAssignedNotification::class);
});

it('notifies nobody when an agent or admin claims, or agent releases a ticket', function () {
    Notification::fake();

    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $rafiToken = $rafi->createToken('rafi')->plainTextToken;

    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => null,
        'status' => TicketStatus::Open,
        'lock_version' => 0,
    ]);

    // Rafi claims
    $claimResponse = $this->withToken($rafiToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/claim");
    $claimResponse->assertOk();

    Notification::assertNothingSent();

    // Rafi releases
    $releaseResponse = $this->withToken($rafiToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/release");
    $releaseResponse->assertOk();

    Notification::assertNothingSent();
});

it('sends confirmation to customer Ana and nothing to agent when agent creates ticket for Ana', function () {
    Notification::fake();

    $agent = User::factory()->agent()->create(['name' => 'Support Agent']);
    $agentToken = $agent->createToken('agent')->plainTextToken;
    $ana = User::factory()->customer()->create(['name' => 'Ana']);

    $response = $this->withToken($agentToken)
        ->postJson('/api/v1/tickets', [
            'subject' => 'Call from Ana',
            'description' => 'Ana called about billing issue.',
            'category_id' => $this->category->id,
            'customer_id' => $ana->id,
        ]);

    $response->assertCreated();

    Notification::assertSentToTimes($ana, TicketCreatedNotification::class, 1);
    Notification::assertNotSentTo($agent, TicketCreatedNotification::class);
});

it('sends StaffRepliedNotification to customer Ana when Rafi replies', function () {
    Notification::fake();

    $ana = User::factory()->customer()->create(['name' => 'Ana']);
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $rafiToken = $rafi->createToken('rafi')->plainTextToken;

    $ticket = Ticket::factory()->create([
        'customer_id' => $ana->id,
        'category_id' => $this->category->id,
        'assigned_agent_id' => $rafi->id,
        'status' => TicketStatus::InProgress,
    ]);

    $response = $this->withToken($rafiToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'type' => 'reply',
            'body' => 'Here is the solution to your issue.',
        ]);

    $response->assertCreated();

    Notification::assertSentToTimes($ana, \App\Notifications\Tickets\StaffRepliedNotification::class, 1);
    Notification::assertNotSentTo($rafi, \App\Notifications\Tickets\StaffRepliedNotification::class);
});

it('sends CustomerRepliedNotification to Rafi and nothing to Ana when Ana replies on assigned ticket', function () {
    Notification::fake();

    $ana = User::factory()->customer()->create(['name' => 'Ana']);
    $anaToken = $ana->createToken('ana')->plainTextToken;
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);

    $ticket = Ticket::factory()->create([
        'customer_id' => $ana->id,
        'category_id' => $this->category->id,
        'assigned_agent_id' => $rafi->id,
        'status' => TicketStatus::InProgress,
    ]);

    $response = $this->withToken($anaToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'type' => 'reply',
            'body' => 'Thank you, I tried that and it worked!',
        ]);

    $response->assertCreated();

    Notification::assertSentToTimes($rafi, \App\Notifications\Tickets\CustomerRepliedNotification::class, 1);
    Notification::assertNotSentTo($ana, \App\Notifications\Tickets\CustomerRepliedNotification::class);
});

it('notifies every active Admin when Ana replies on unassigned ticket', function () {
    Notification::fake();

    $ana = User::factory()->customer()->create(['name' => 'Ana']);
    $anaToken = $ana->createToken('ana')->plainTextToken;
    $admin1 = User::factory()->admin()->create(['name' => 'Admin 1']);
    $admin2 = User::factory()->admin()->create(['name' => 'Admin 2']);
    $inactiveAdmin = User::factory()->admin()->create(['name' => 'Inactive Admin', 'is_active' => false]);

    $ticket = Ticket::factory()->create([
        'customer_id' => $ana->id,
        'category_id' => $this->category->id,
        'assigned_agent_id' => null,
        'status' => TicketStatus::Open,
    ]);

    $response = $this->withToken($anaToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'type' => 'reply',
            'body' => 'Following up on my open ticket.',
        ]);

    $response->assertCreated();

    Notification::assertSentToTimes($admin1, \App\Notifications\Tickets\CustomerRepliedNotification::class, 1);
    Notification::assertSentToTimes($admin2, \App\Notifications\Tickets\CustomerRepliedNotification::class, 1);
    Notification::assertNotSentTo($inactiveAdmin, \App\Notifications\Tickets\CustomerRepliedNotification::class);
    Notification::assertNotSentTo($ana, \App\Notifications\Tickets\CustomerRepliedNotification::class);
});

it('notifies Ana and no Admin when an admin replies on Ana unassigned ticket', function () {
    Notification::fake();

    $ana = User::factory()->customer()->create(['name' => 'Ana']);
    $admin = User::factory()->admin()->create(['name' => 'Admin Responder']);
    $adminToken = $admin->createToken('admin')->plainTextToken;
    $otherAdmin = User::factory()->admin()->create(['name' => 'Other Admin']);

    $ticket = Ticket::factory()->create([
        'customer_id' => $ana->id,
        'category_id' => $this->category->id,
        'assigned_agent_id' => null,
        'status' => TicketStatus::Open,
    ]);

    $response = $this->withToken($adminToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'type' => 'reply',
            'body' => 'We are looking into this.',
        ]);

    $response->assertCreated();

    Notification::assertSentToTimes($ana, \App\Notifications\Tickets\StaffRepliedNotification::class, 1);
    Notification::assertNotSentTo($admin, \App\Notifications\Tickets\StaffRepliedNotification::class);
    Notification::assertNotSentTo($otherAdmin, \App\Notifications\Tickets\StaffRepliedNotification::class);
});

it('notifies Rafi and not Ana on admin internal note, and notifies nobody on Rafi own note', function () {
    Notification::fake();

    $ana = User::factory()->customer()->create(['name' => 'Ana']);
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $rafiToken = $rafi->createToken('rafi')->plainTextToken;
    $admin = User::factory()->admin()->create(['name' => 'Admin']);
    $adminToken = $admin->createToken('admin')->plainTextToken;

    $ticket = Ticket::factory()->create([
        'customer_id' => $ana->id,
        'category_id' => $this->category->id,
        'assigned_agent_id' => $rafi->id,
        'status' => TicketStatus::InProgress,
    ]);

    // Admin posts internal note
    $noteResponse = $this->withToken($adminToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'type' => 'internal_note',
            'body' => 'Check account permissions on user.',
        ]);
    $noteResponse->assertCreated();

    Notification::assertSentToTimes($rafi, \App\Notifications\Tickets\InternalNoteAddedNotification::class, 1);
    Notification::assertNotSentTo($ana, \App\Notifications\Tickets\InternalNoteAddedNotification::class);
    Notification::assertNotSentTo($admin, \App\Notifications\Tickets\InternalNoteAddedNotification::class);

    // Rafi posts note on his own ticket
    $this->app['auth']->forgetGuards();
    $rafiNoteResponse = $this->withToken($rafiToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'type' => 'internal_note',
            'body' => 'I have verified the permissions.',
        ]);
    $rafiNoteResponse->assertCreated();

    // Rafi still only received 1 from admin note, not 2
    Notification::assertSentToTimes($rafi, \App\Notifications\Tickets\InternalNoteAddedNotification::class, 1);
});

it('notifies nobody when Ana replies on ticket held by deactivated Rafi', function () {
    Notification::fake();

    $ana = User::factory()->customer()->create(['name' => 'Ana']);
    $anaToken = $ana->createToken('ana')->plainTextToken;
    $deactivatedRafi = User::factory()->agent()->create(['name' => 'Rafi', 'is_active' => false]);

    $ticket = Ticket::factory()->create([
        'customer_id' => $ana->id,
        'category_id' => $this->category->id,
        'assigned_agent_id' => $deactivatedRafi->id,
        'status' => TicketStatus::InProgress,
    ]);

    $response = $this->withToken($anaToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'type' => 'reply',
            'body' => 'Following up on my ticket.',
        ]);

    $response->assertCreated();

    Notification::assertNothingSent();
});

it('sends TicketResolvedNotification to Ana when Rafi resolves her ticket, and sends nothing for other status transitions', function () {
    Notification::fake();

    $ana = User::factory()->customer()->create(['name' => 'Ana']);
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $rafiToken = $rafi->createToken('rafi')->plainTextToken;

    $ticket = Ticket::factory()->create([
        'customer_id' => $ana->id,
        'category_id' => $this->category->id,
        'assigned_agent_id' => $rafi->id,
        'status' => TicketStatus::InProgress,
        'lock_version' => 0,
    ]);

    // Move to waiting_for_customer - sends nothing
    $waitingResponse = $this->withToken($rafiToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/status", [
            'status' => 'waiting_for_customer',
            'lock_version' => 0,
        ]);
    $waitingResponse->assertOk();
    Notification::assertNothingSent();

    // Move to in_progress - sends nothing
    $inProgressResponse = $this->withToken($rafiToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/status", [
            'status' => 'in_progress',
            'lock_version' => 1,
        ]);
    $inProgressResponse->assertOk();
    Notification::assertNothingSent();

    // Move to resolved - sends TicketResolvedNotification to Ana
    $resolvedResponse = $this->withToken($rafiToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/status", [
            'status' => 'resolved',
            'lock_version' => 2,
        ]);
    $resolvedResponse->assertOk();

    Notification::assertSentToTimes($ana, \App\Notifications\Tickets\TicketResolvedNotification::class, 1);
    Notification::assertNotSentTo($rafi, \App\Notifications\Tickets\TicketResolvedNotification::class);
});

it('sends nothing when a status change is rejected with 409 for stale lock_version', function () {
    Notification::fake();

    $ana = User::factory()->customer()->create(['name' => 'Ana']);
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $rafiToken = $rafi->createToken('rafi')->plainTextToken;

    $ticket = Ticket::factory()->create([
        'customer_id' => $ana->id,
        'category_id' => $this->category->id,
        'assigned_agent_id' => $rafi->id,
        'status' => TicketStatus::InProgress,
        'lock_version' => 1,
    ]);

    $conflictResponse = $this->withToken($rafiToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/status", [
            'status' => 'resolved',
            'lock_version' => 0, // Stale version
        ]);

    $conflictResponse->assertStatus(409);
    Notification::assertNothingSent();
});

it('sends nothing when a reply is rejected with 422', function () {
    Notification::fake();

    $ana = User::factory()->customer()->create(['name' => 'Ana']);
    $anaToken = $ana->createToken('ana')->plainTextToken;

    $ticket = Ticket::factory()->create([
        'customer_id' => $ana->id,
        'category_id' => $this->category->id,
        'status' => TicketStatus::InProgress,
    ]);

    $invalidResponse = $this->withToken($anaToken)
        ->postJson("/api/v1/tickets/{$ticket->ticket_number}/messages", [
            'type' => 'invalid_type',
            'body' => '',
        ]);

    $invalidResponse->assertStatus(422);
    Notification::assertNothingSent();
});
