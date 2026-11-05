<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('database seeder sets up demo agents, assigned tickets and activities', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'admin@techzu-sla.test')->firstOrFail();
    $adminToken = $admin->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/api/v1/admin/agents')
        ->assertOk();

    $agents = $response->json();
    expect($agents)->toHaveCount(3);

    // Every demo agent with at least one category
    foreach ($agents as $agent) {
        expect($agent['categories'])->not->toBeEmpty();
    }

    // Check specific agent settings from D8
    $agent1 = collect($agents)->firstWhere('name', 'Support Agent 1');
    $agent2 = collect($agents)->firstWhere('name', 'Support Agent 2');
    $agent3 = collect($agents)->firstWhere('name', 'Support Agent 3');

    expect($agent1['max_open_tickets'])->toBe(15)
        ->and($agent1['is_available'])->toBeTrue()
        ->and(collect($agent1['categories'])->pluck('name')->all())->toEqualCanonicalizing(['Billing', 'Account'])
        ->and($agent2['max_open_tickets'])->toBe(15)
        ->and($agent2['is_available'])->toBeTrue()
        ->and(collect($agent2['categories'])->pluck('name')->all())->toEqualCanonicalizing(['Technical'])
        ->and($agent3['max_open_tickets'])->toBeNull()
        ->and($agent3['is_available'])->toBeFalse()
        ->and(collect($agent3['categories'])->pluck('name')->all())->toEqualCanonicalizing(['General', 'Technical']);

    // Sum of active_ticket_count equals the number of assigned active demo tickets
    $totalActiveInAgents = collect($agents)->sum('active_ticket_count');
    $assignedActiveTicketsCount = Ticket::query()
        ->whereNotNull('assigned_agent_id')
        ->whereIn('status', [TicketStatus::Open->value, TicketStatus::InProgress->value, TicketStatus::WaitingForCustomer->value])
        ->count();

    expect($totalActiveInAgents)->toBe($assignedActiveTicketsCount);

    // Sampled assigned ticket's activities include an assigned entry and first_assigned_at is set
    $assignedTicket = Ticket::whereNotNull('assigned_agent_id')->firstOrFail();
    expect($assignedTicket->first_assigned_at)->not->toBeNull();

    $activitiesResponse = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/api/v1/tickets/{$assignedTicket->ticket_number}/activities")
        ->assertOk();

    $assignedActivity = collect($activitiesResponse->json())->firstWhere('type', 'assigned');
    expect($assignedActivity)->not->toBeNull();

    // Check last_assigned_at set on each agent who has tickets
    $agentUser1 = User::where('email', 'agent1@techzu-sla.test')->firstOrFail();
    expect($agentUser1->last_assigned_at)->not->toBeNull();
});

test('database seeder seeds conversations and attachments with proper visibility and downloads', function () {
    \Illuminate\Support\Facades\Storage::fake(config('attachments.disk', 'local'));

    $this->seed(DatabaseSeeder::class);

    // Find a ticket that has both customer/agent replies and an internal note
    $tickets = Ticket::with(['messages.author', 'attachments'])->get();
    $targetTicket = $tickets->first(function (Ticket $ticket) {
        $messages = $ticket->messages;
        $hasCustomerReply = $messages->contains(fn ($m) => $m->type === \App\Enums\TicketMessageType::Reply && $m->author_id === $ticket->customer_id);
        $hasAgentReply = $messages->contains(fn ($m) => $m->type === \App\Enums\TicketMessageType::Reply && $m->author->role === \App\Enums\UserRole::Agent);
        $hasInternalNote = $messages->contains(fn ($m) => $m->type === \App\Enums\TicketMessageType::InternalNote);

        return $hasCustomerReply && $hasAgentReply && $hasInternalNote;
    });

    expect($targetTicket)->not->toBeNull();

    $customer = $targetTicket->customer;
    $customerToken = $customer->createToken('test')->plainTextToken;

    $agent = User::where('role', \App\Enums\UserRole::Agent)->firstOrFail();
    $agentToken = $agent->createToken('test')->plainTextToken;

    // Customer's messages list is shorter than an agent's
    $customerMessages = $this->withHeader('Authorization', "Bearer {$customerToken}")
        ->getJson("/api/v1/tickets/{$targetTicket->ticket_number}/messages")
        ->assertOk()
        ->json('data');

    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $agentMessages = $this->withHeader('Authorization', "Bearer {$agentToken}")
        ->getJson("/api/v1/tickets/{$targetTicket->ticket_number}/messages")
        ->assertOk()
        ->json('data');

    expect(count($customerMessages))->toBeLessThan(count($agentMessages));

    // An agent can download a seeded attachment with its recorded name
    $attachment = \App\Models\Attachment::firstOrFail();
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $download = $this->withHeader('Authorization', "Bearer {$agentToken}")
        ->get("/api/v1/attachments/{$attachment->id}/download")
        ->assertOk();

    expect($download->headers->get('Content-Disposition'))
        ->toBe("attachment; filename={$attachment->name}");
});

test('database seeder seeds escalated tickets, sla warnings, and an impending-deadline ticket', function () {
    \Illuminate\Support\Facades\Notification::fake();
    \Illuminate\Support\Facades\Storage::fake(config('attachments.disk', 'local'));

    $this->seed(DatabaseSeeder::class);

    // At least one active ticket is escalated with escalated in its history
    $escalatedTicket = Ticket::query()
        ->whereIn('status', [TicketStatus::Open->value, TicketStatus::InProgress->value, TicketStatus::WaitingForCustomer->value])
        ->whereNotNull('escalated_at')
        ->first();

    expect($escalatedTicket)->not->toBeNull()
        ->and($escalatedTicket->activities()->where('type', \App\Enums\TicketActivityType::Escalated)->exists())->toBeTrue();

    // At least one ticket has an sla_warning entry
    $hasWarning = \App\Models\TicketActivity::where('type', \App\Enums\TicketActivityType::SlaWarning)->exists();
    expect($hasWarning)->toBeTrue();

    // One active assigned ticket has warning time within 5 minutes and due time within 15 minutes
    $now = now();
    $impending = Ticket::query()
        ->whereIn('status', [TicketStatus::Open->value, TicketStatus::InProgress->value, TicketStatus::WaitingForCustomer->value])
        ->whereNotNull('assigned_agent_id')
        ->whereBetween('sla_warning_at', [$now, $now->copy()->addMinutes(5)])
        ->whereBetween('sla_due_at', [$now, $now->copy()->addMinutes(15)])
        ->first();

    expect($impending)->not->toBeNull();
});


