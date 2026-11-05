<?php

use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;

beforeEach(function () {
    $this->category = Category::factory()->create(['name' => 'Billing', 'is_active' => true]);
});

it('assigns never-assigned active tickets after an agent becomes available', function () {
    // Ticket created while nobody was available (first_assigned_at is null)
    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => null,
        'first_assigned_at' => null,
        'status' => TicketStatus::Open,
        'lock_version' => 0,
    ]);

    $rafi = User::factory()->agent()->unavailable()->create(['name' => 'Rafi']);
    $rafi->categories()->attach($this->category);

    // Command runs while Rafi is still unavailable -> nothing happens
    $this->artisan('tickets:assign-unassigned')->assertSuccessful();
    expect($ticket->refresh()->assigned_agent_id)->toBeNull();

    // Rafi becomes available
    $rafi->is_available = true;
    $rafi->save();

    // Command runs again -> ticket assigned to Rafi
    $this->artisan('tickets:assign-unassigned')->assertSuccessful();

    expect($ticket->refresh()->assigned_agent_id)->toBe($rafi->id)
        ->and($ticket->first_assigned_at)->not->toBeNull();

    $activities = $ticket->activities()->get();
    expect($activities)->toHaveCount(1)
        ->and($activities[0]->type->value)->toBe('assigned');
});

it('leaves released tickets unassigned and resolved or closed tickets untouched', function () {
    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $rafi->categories()->attach($this->category);

    // Released ticket (has first_assigned_at set)
    $releasedTicket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => null,
        'first_assigned_at' => now()->subHour(),
        'status' => TicketStatus::Open,
    ]);

    // Resolved ticket with first_assigned_at null
    $resolvedTicket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => null,
        'first_assigned_at' => null,
        'status' => TicketStatus::Resolved,
    ]);

    // Closed ticket with first_assigned_at null
    $closedTicket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => null,
        'first_assigned_at' => null,
        'status' => TicketStatus::Closed,
    ]);

    $this->artisan('tickets:assign-unassigned')->assertSuccessful();

    expect($releasedTicket->refresh()->assigned_agent_id)->toBeNull()
        ->and($resolvedTicket->refresh()->assigned_agent_id)->toBeNull()
        ->and($closedTicket->refresh()->assigned_agent_id)->toBeNull();
});

it('adds exactly one assigned entry when command runs twice', function () {
    $ticket = Ticket::factory()->create([
        'category_id' => $this->category->id,
        'assigned_agent_id' => null,
        'first_assigned_at' => null,
        'status' => TicketStatus::Open,
    ]);

    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $rafi->categories()->attach($this->category);

    $this->artisan('tickets:assign-unassigned')->assertSuccessful();
    $this->artisan('tickets:assign-unassigned')->assertSuccessful();

    expect($ticket->refresh()->assigned_agent_id)->toBe($rafi->id);

    $assignedActivities = $ticket->activities()->where('type', 'assigned')->get();
    expect($assignedActivities)->toHaveCount(1);
});

it('is scheduled to run every five minutes without overlapping', function () {
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

    $event = collect($schedule->events())->first(function ($event) {
        return str_contains($event->command ?? '', 'tickets:assign-unassigned');
    });

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/5 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
