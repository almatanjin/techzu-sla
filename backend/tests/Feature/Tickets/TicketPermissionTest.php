<?php

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;

it('returns expected assignment permissions across user roles and ticket states', function (
    Closure $setupUserAndTicket,
    bool $expectedCanAssign,
    bool $expectedCanClaim,
    bool $expectedCanRelease
) {
    $category = Category::factory()->create();
    [$user, $ticket] = $setupUserAndTicket($category);

    $token = $user->createToken('test-token')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}");

    $response->assertOk()
        ->assertJson([
            'permissions' => [
                'can_assign' => $expectedCanAssign,
                'can_claim' => $expectedCanClaim,
                'can_release' => $expectedCanRelease,
            ],
        ]);
})->with([
    'agent on an unassigned open ticket' => [
        function (Category $category) {
            $agent = User::factory()->agent()->create();
            $ticket = Ticket::factory()->create([
                'category_id' => $category->id,
                'assigned_agent_id' => null,
                'status' => TicketStatus::Open,
            ]);
            return [$agent, $ticket];
        },
        false, // can_assign
        true,  // can_claim
        false, // can_release
    ],
    'assignee on their in-progress ticket' => [
        function (Category $category) {
            $agent = User::factory()->agent()->create();
            $ticket = Ticket::factory()->create([
                'category_id' => $category->id,
                'assigned_agent_id' => $agent->id,
                'status' => TicketStatus::InProgress,
            ]);
            return [$agent, $ticket];
        },
        false, // can_assign
        false, // can_claim
        true,  // can_release
    ],
    'another agent on that ticket' => [
        function (Category $category) {
            $assignee = User::factory()->agent()->create();
            $otherAgent = User::factory()->agent()->create();
            $ticket = Ticket::factory()->create([
                'category_id' => $category->id,
                'assigned_agent_id' => $assignee->id,
                'status' => TicketStatus::InProgress,
            ]);
            return [$otherAgent, $ticket];
        },
        false, // can_assign
        false, // can_claim
        false, // can_release
    ],
    'admin on an unassigned ticket' => [
        function (Category $category) {
            $admin = User::factory()->admin()->create();
            $ticket = Ticket::factory()->create([
                'category_id' => $category->id,
                'assigned_agent_id' => null,
                'status' => TicketStatus::Open,
            ]);
            return [$admin, $ticket];
        },
        true,  // can_assign
        true,  // can_claim
        false, // can_release
    ],
    'admin on a closed ticket' => [
        function (Category $category) {
            $admin = User::factory()->admin()->create();
            $ticket = Ticket::factory()->create([
                'category_id' => $category->id,
                'assigned_agent_id' => null,
                'status' => TicketStatus::Closed,
            ]);
            return [$admin, $ticket];
        },
        false, // can_assign
        false, // can_claim
        false, // can_release
    ],
    'the owning customer' => [
        function (Category $category) {
            $customer = User::factory()->customer()->create();
            $ticket = Ticket::factory()->create([
                'category_id' => $category->id,
                'customer_id' => $customer->id,
                'assigned_agent_id' => null,
                'status' => TicketStatus::Open,
            ]);
            return [$customer, $ticket];
        },
        false, // can_assign
        false, // can_claim
        false, // can_release
    ],
]);

it('returns expected conversation permissions across user roles and ticket states', function (
    Closure $setupUserAndTicket,
    bool $expectedCanReply,
    bool $expectedCanAddInternalNote
) {
    $category = Category::factory()->create();
    [$user, $ticket] = $setupUserAndTicket($category);

    $token = $user->createToken('test-token')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson("/api/v1/tickets/{$ticket->ticket_number}");

    $response->assertOk()
        ->assertJson([
            'permissions' => [
                'can_reply' => $expectedCanReply,
                'can_add_internal_note' => $expectedCanAddInternalNote,
            ],
        ]);
})->with([
    'Ana on her open ticket' => [
        function (Category $category) {
            $ana = User::factory()->customer()->create(['name' => 'Ana']);
            $ticket = Ticket::factory()->open()->create([
                'category_id' => $category->id,
                'customer_id' => $ana->id,
            ]);
            return [$ana, $ticket];
        },
        true,  // can_reply
        false, // can_add_internal_note
    ],
    'Ana on her resolved ticket' => [
        function (Category $category) {
            $ana = User::factory()->customer()->create(['name' => 'Ana']);
            $ticket = Ticket::factory()->resolved()->create([
                'category_id' => $category->id,
                'customer_id' => $ana->id,
            ]);
            return [$ana, $ticket];
        },
        true,  // can_reply
        false, // can_add_internal_note
    ],
    'an agent on an open ticket' => [
        function (Category $category) {
            $agent = User::factory()->agent()->create();
            $ticket = Ticket::factory()->open()->create([
                'category_id' => $category->id,
            ]);
            return [$agent, $ticket];
        },
        true, // can_reply
        true, // can_add_internal_note
    ],
    'an admin on an in-progress ticket' => [
        function (Category $category) {
            $admin = User::factory()->admin()->create();
            $ticket = Ticket::factory()->inProgress()->create([
                'category_id' => $category->id,
            ]);
            return [$admin, $ticket];
        },
        true, // can_reply
        true, // can_add_internal_note
    ],
    'an agent on a closed ticket' => [
        function (Category $category) {
            $agent = User::factory()->agent()->create();
            $ticket = Ticket::factory()->closed()->create([
                'category_id' => $category->id,
            ]);
            return [$agent, $ticket];
        },
        false, // can_reply
        false, // can_add_internal_note
    ],
    'Ana on her closed ticket' => [
        function (Category $category) {
            $ana = User::factory()->customer()->create(['name' => 'Ana']);
            $ticket = Ticket::factory()->closed()->create([
                'category_id' => $category->id,
                'customer_id' => $ana->id,
            ]);
            return [$ana, $ticket];
        },
        false, // can_reply
        false, // can_add_internal_note
    ],
]);

