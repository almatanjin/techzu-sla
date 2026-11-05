<?php

use App\Enums\TicketActivityType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Tickets\SlaWarningNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

it('warns a critical ticket assigned to Rafi when warning time passes', function () {
    Notification::fake();

    $tz = config('tickets.timezone', 'Asia/Dhaka');
    $sundayMorning = CarbonImmutable::parse('2026-11-08 09:00:00', $tz);
    $this->travelTo($sundayMorning);

    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $category = Category::factory()->create(['name' => 'Billing', 'is_active' => true]);

    $ticket = Ticket::factory()->create([
        'subject' => 'Critical production bug',
        'priority' => TicketPriority::Critical,
        'status' => TicketStatus::InProgress,
        'category_id' => $category->id,
        'assigned_agent_id' => $rafi->id,
        'sla_policy_minutes' => 120,
        'sla_started_at' => $sundayMorning->setTimezone('UTC'),
        'sla_warning_at' => $sundayMorning->addMinutes(90)->setTimezone('UTC'), // 10:30 Asia/Dhaka
        'sla_due_at' => $sundayMorning->addMinutes(120)->setTimezone('UTC'), // 11:00 Asia/Dhaka
    ]);

    $warningTimePassed = CarbonImmutable::parse('2026-11-08 10:31:00', $tz);
    $this->travelTo($warningTimePassed);

    $this->artisan('tickets:check-sla')->assertSuccessful();

    $ticket->refresh();
    expect($ticket->sla_warning_notified_at)->not->toBeNull()
        ->and($ticket->sla_warning_notified_at->timezone($tz)->format('Y-m-d H:i'))
        ->toBe('2026-11-08 10:31');

    $lastActivity = $ticket->activities()->latest('id')->first();
    expect($lastActivity)->not->toBeNull()
        ->and($lastActivity->type)->toBe(TicketActivityType::SlaWarning)
        ->and($lastActivity->actor_id)->toBeNull()
        ->and($lastActivity->new_values['due_at'])->toBe($ticket->sla_due_at->toISOString());

    Notification::assertSentTo($rafi, SlaWarningNotification::class, 1);
});

it('notifies every active Admin and not a deactivated Admin when unassigned ticket reaches warning time', function () {
    Notification::fake();

    $tz = config('tickets.timezone', 'Asia/Dhaka');
    $sundayMorning = CarbonImmutable::parse('2026-11-08 09:00:00', $tz);
    $this->travelTo($sundayMorning);

    $admin1 = User::factory()->admin()->create(['name' => 'Active Admin 1']);
    $admin2 = User::factory()->admin()->create(['name' => 'Active Admin 2']);
    $deactivatedAdmin = User::factory()->admin()->create(['name' => 'Inactive Admin', 'is_active' => false]);
    $category = Category::factory()->create(['name' => 'Billing', 'is_active' => true]);

    $ticket = Ticket::factory()->create([
        'subject' => 'Unassigned critical bug',
        'priority' => TicketPriority::Critical,
        'status' => TicketStatus::Open,
        'category_id' => $category->id,
        'assigned_agent_id' => null,
        'sla_policy_minutes' => 120,
        'sla_started_at' => $sundayMorning->setTimezone('UTC'),
        'sla_warning_at' => $sundayMorning->addMinutes(90)->setTimezone('UTC'),
        'sla_due_at' => $sundayMorning->addMinutes(120)->setTimezone('UTC'),
    ]);

    $warningTimePassed = CarbonImmutable::parse('2026-11-08 10:31:00', $tz);
    $this->travelTo($warningTimePassed);

    $this->artisan('tickets:check-sla')->assertSuccessful();

    $ticket->refresh();
    expect($ticket->sla_warning_notified_at)->not->toBeNull();

    Notification::assertSentTo($admin1, SlaWarningNotification::class, 1);
    Notification::assertSentTo($admin2, SlaWarningNotification::class, 1);
    Notification::assertNotSentTo($deactivatedAdmin, SlaWarningNotification::class);
});

it('does nothing at Sunday 10:00 before warning time arrives', function () {
    Notification::fake();

    $tz = config('tickets.timezone', 'Asia/Dhaka');
    $sundayMorning = CarbonImmutable::parse('2026-11-08 09:00:00', $tz);
    $this->travelTo($sundayMorning);

    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $category = Category::factory()->create(['name' => 'Billing', 'is_active' => true]);

    $ticket = Ticket::factory()->create([
        'subject' => 'Critical production bug',
        'priority' => TicketPriority::Critical,
        'status' => TicketStatus::InProgress,
        'category_id' => $category->id,
        'assigned_agent_id' => $rafi->id,
        'sla_policy_minutes' => 120,
        'sla_started_at' => $sundayMorning->setTimezone('UTC'),
        'sla_warning_at' => $sundayMorning->addMinutes(90)->setTimezone('UTC'), // 10:30
        'sla_due_at' => $sundayMorning->addMinutes(120)->setTimezone('UTC'), // 11:00
    ]);

    $beforeWarning = CarbonImmutable::parse('2026-11-08 10:00:00', $tz);
    $this->travelTo($beforeWarning);

    $this->artisan('tickets:check-sla')->assertSuccessful();

    $ticket->refresh();
    expect($ticket->sla_warning_notified_at)->toBeNull()
        ->and($ticket->activities()->where('type', TicketActivityType::SlaWarning)->exists())->toBeFalse();

    Notification::assertNothingSent();
});

it('does not warn a ticket that was resolved at 10:25 when checked at 10:31', function () {
    Notification::fake();

    $tz = config('tickets.timezone', 'Asia/Dhaka');
    $sundayMorning = CarbonImmutable::parse('2026-11-08 09:00:00', $tz);
    $this->travelTo($sundayMorning);

    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $category = Category::factory()->create(['name' => 'Billing', 'is_active' => true]);

    $ticket = Ticket::factory()->create([
        'subject' => 'Critical production bug',
        'priority' => TicketPriority::Critical,
        'status' => TicketStatus::Open,
        'category_id' => $category->id,
        'assigned_agent_id' => $rafi->id,
        'sla_policy_minutes' => 120,
        'sla_started_at' => $sundayMorning->setTimezone('UTC'),
        'sla_warning_at' => $sundayMorning->addMinutes(90)->setTimezone('UTC'), // 10:30
        'sla_due_at' => $sundayMorning->addMinutes(120)->setTimezone('UTC'), // 11:00
    ]);

    $resolvedTime = CarbonImmutable::parse('2026-11-08 10:25:00', $tz);
    $this->travelTo($resolvedTime);
    $ticket->update([
        'status' => TicketStatus::Resolved,
        'resolved_at' => $resolvedTime->setTimezone('UTC'),
    ]);

    $checkTime = CarbonImmutable::parse('2026-11-08 10:31:00', $tz);
    $this->travelTo($checkTime);

    $this->artisan('tickets:check-sla')->assertSuccessful();

    $ticket->refresh();
    expect($ticket->sla_warning_notified_at)->toBeNull()
        ->and($ticket->activities()->where('type', TicketActivityType::SlaWarning)->exists())->toBeFalse();

    Notification::assertNothingSent();
});

it('records and sends nothing on a second run at 10:32', function () {
    Notification::fake();

    $tz = config('tickets.timezone', 'Asia/Dhaka');
    $sundayMorning = CarbonImmutable::parse('2026-11-08 09:00:00', $tz);
    $this->travelTo($sundayMorning);

    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $category = Category::factory()->create(['name' => 'Billing', 'is_active' => true]);

    $ticket = Ticket::factory()->create([
        'subject' => 'Critical production bug',
        'priority' => TicketPriority::Critical,
        'status' => TicketStatus::InProgress,
        'category_id' => $category->id,
        'assigned_agent_id' => $rafi->id,
        'sla_policy_minutes' => 120,
        'sla_started_at' => $sundayMorning->setTimezone('UTC'),
        'sla_warning_at' => $sundayMorning->addMinutes(90)->setTimezone('UTC'), // 10:30
        'sla_due_at' => $sundayMorning->addMinutes(120)->setTimezone('UTC'), // 11:00
    ]);

    $firstRunTime = CarbonImmutable::parse('2026-11-08 10:31:00', $tz);
    $this->travelTo($firstRunTime);
    $this->artisan('tickets:check-sla')->assertSuccessful();

    $ticket->refresh();
    expect($ticket->sla_warning_notified_at)->not->toBeNull();
    Notification::assertSentToTimes($rafi, SlaWarningNotification::class, 1);
    expect($ticket->activities()->where('type', TicketActivityType::SlaWarning)->count())->toBe(1);

    // Second run a minute later
    $secondRunTime = CarbonImmutable::parse('2026-11-08 10:32:00', $tz);
    $this->travelTo($secondRunTime);
    $this->artisan('tickets:check-sla')->assertSuccessful();

    Notification::assertSentToTimes($rafi, SlaWarningNotification::class, 1);
    expect($ticket->activities()->where('type', TicketActivityType::SlaWarning)->count())->toBe(1);
});

it('lists tickets:check-sla in schedule:list to run every minute', function () {
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
    $events = collect($schedule->events());
    $checkEvent = $events->first(fn ($event) => str_contains($event->command ?? '', 'tickets:check-sla'));

    expect($checkEvent)->not->toBeNull()
        ->and($checkEvent->expression)->toBe('* * * * *');
});

it('escalates an in-progress breached critical ticket on breach', function () {
    Notification::fake();

    $tz = config('tickets.timezone', 'Asia/Dhaka');
    $sundayMorning = CarbonImmutable::parse('2026-11-08 09:00:00', $tz);
    $this->travelTo($sundayMorning);

    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $admin = User::factory()->admin()->create(['name' => 'Active Admin']);
    $category = Category::factory()->create(['name' => 'Billing', 'is_active' => true]);

    $ticket = Ticket::factory()->create([
        'subject' => 'Critical outage',
        'priority' => TicketPriority::Critical,
        'status' => TicketStatus::InProgress,
        'category_id' => $category->id,
        'assigned_agent_id' => $rafi->id,
        'lock_version' => 0,
        'sla_policy_minutes' => 120,
        'sla_started_at' => $sundayMorning->setTimezone('UTC'),
        'sla_warning_at' => $sundayMorning->addMinutes(90)->setTimezone('UTC'), // 10:30
        'sla_due_at' => $sundayMorning->addMinutes(120)->setTimezone('UTC'), // 11:00
        'sla_warning_notified_at' => $sundayMorning->addMinutes(91)->setTimezone('UTC'),
    ]);

    $breachTime = CarbonImmutable::parse('2026-11-08 11:01:00', $tz);
    $this->travelTo($breachTime);

    $this->artisan('tickets:check-sla')->assertSuccessful();

    $ticket->refresh();
    expect($ticket->sla_breached_at)->not->toBeNull()
        ->and($ticket->sla_breached_at->timezone($tz)->format('Y-m-d H:i'))->toBe('2026-11-08 11:01')
        ->and($ticket->escalated_at)->not->toBeNull()
        ->and($ticket->escalated_at->timezone($tz)->format('Y-m-d H:i'))->toBe('2026-11-08 11:01')
        ->and($ticket->assigned_agent_id)->toBeNull()
        ->and($ticket->lock_version)->toBe(1)
        ->and($ticket->status)->toBe(TicketStatus::InProgress)
        ->and($ticket->priority)->toBe(TicketPriority::Critical);

    $activities = $ticket->activities()->latest('id')->take(2)->get()->reverse()->values();
    expect($activities->count())->toBe(2);

    $unassignedActivity = $activities[0];
    expect($unassignedActivity->type)->toBe(TicketActivityType::Unassigned)
        ->and($unassignedActivity->actor_id)->toBeNull()
        ->and($unassignedActivity->old_values)->toBe(['agent' => ['id' => $rafi->id, 'name' => 'Rafi']])
        ->and($unassignedActivity->new_values)->toBeNull();

    $escalatedActivity = $activities[1];
    expect($escalatedActivity->type)->toBe(TicketActivityType::Escalated)
        ->and($escalatedActivity->actor_id)->toBeNull()
        ->and($escalatedActivity->new_values['due_at'])->toBe($ticket->sla_due_at->toISOString());

    Notification::assertSentToTimes($rafi, \App\Notifications\Tickets\TicketEscalatedNotification::class, 1);
    Notification::assertSentToTimes($admin, \App\Notifications\Tickets\TicketEscalatedNotification::class, 1);
});

it('escalates unassigned breached ticket with escalated entry, no unassigned entry, and notifies only admins', function () {
    Notification::fake();

    $tz = config('tickets.timezone', 'Asia/Dhaka');
    $sundayMorning = CarbonImmutable::parse('2026-11-08 09:00:00', $tz);
    $this->travelTo($sundayMorning);

    $admin = User::factory()->admin()->create(['name' => 'Active Admin']);
    $agent = User::factory()->agent()->create(['name' => 'Other Agent']);
    $category = Category::factory()->create(['name' => 'Billing', 'is_active' => true]);

    $ticket = Ticket::factory()->create([
        'subject' => 'Unassigned critical bug',
        'priority' => TicketPriority::Critical,
        'status' => TicketStatus::Open,
        'category_id' => $category->id,
        'assigned_agent_id' => null,
        'lock_version' => 0,
        'sla_policy_minutes' => 120,
        'sla_started_at' => $sundayMorning->setTimezone('UTC'),
        'sla_warning_at' => $sundayMorning->addMinutes(90)->setTimezone('UTC'), // 10:30
        'sla_due_at' => $sundayMorning->addMinutes(120)->setTimezone('UTC'), // 11:00
        'sla_warning_notified_at' => $sundayMorning->addMinutes(91)->setTimezone('UTC'),
    ]);

    $breachTime = CarbonImmutable::parse('2026-11-08 11:01:00', $tz);
    $this->travelTo($breachTime);

    $this->artisan('tickets:check-sla')->assertSuccessful();

    $ticket->refresh();
    expect($ticket->sla_breached_at)->not->toBeNull()
        ->and($ticket->escalated_at)->not->toBeNull()
        ->and($ticket->assigned_agent_id)->toBeNull();

    expect($ticket->activities()->where('type', TicketActivityType::Unassigned)->exists())->toBeFalse()
        ->and($ticket->activities()->where('type', TicketActivityType::Escalated)->exists())->toBeTrue();

    Notification::assertSentToTimes($admin, \App\Notifications\Tickets\TicketEscalatedNotification::class, 1);
    Notification::assertNotSentTo($agent, \App\Notifications\Tickets\TicketEscalatedNotification::class);
});

it('processes sla_warning, unassigned, and escalated in order when both deadlines passed before run', function () {
    Notification::fake();

    $tz = config('tickets.timezone', 'Asia/Dhaka');
    $sundayMorning = CarbonImmutable::parse('2026-11-08 09:00:00', $tz);
    $this->travelTo($sundayMorning);

    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $category = Category::factory()->create(['name' => 'Billing', 'is_active' => true]);

    $ticket = Ticket::factory()->create([
        'subject' => 'Offline scheduler ticket',
        'priority' => TicketPriority::Critical,
        'status' => TicketStatus::InProgress,
        'category_id' => $category->id,
        'assigned_agent_id' => $rafi->id,
        'lock_version' => 0,
        'sla_policy_minutes' => 120,
        'sla_started_at' => $sundayMorning->setTimezone('UTC'),
        'sla_warning_at' => $sundayMorning->addMinutes(90)->setTimezone('UTC'), // 10:30
        'sla_due_at' => $sundayMorning->addMinutes(120)->setTimezone('UTC'), // 11:00
        'sla_warning_notified_at' => null,
        'sla_breached_at' => null,
    ]);

    $afterDue = CarbonImmutable::parse('2026-11-08 11:05:00', $tz);
    $this->travelTo($afterDue);

    $this->artisan('tickets:check-sla')->assertSuccessful();

    $ticket->refresh();
    expect($ticket->sla_warning_notified_at)->not->toBeNull()
        ->and($ticket->sla_breached_at)->not->toBeNull()
        ->and($ticket->escalated_at)->not->toBeNull()
        ->and($ticket->assigned_agent_id)->toBeNull();

    $activities = $ticket->activities()->latest('id')->take(3)->get()->reverse()->values();
    expect($activities->count())->toBe(3)
        ->and($activities[0]->type)->toBe(TicketActivityType::SlaWarning)
        ->and($activities[1]->type)->toBe(TicketActivityType::Unassigned)
        ->and($activities[2]->type)->toBe(TicketActivityType::Escalated);
});

it('changes nothing and sends nothing on second run after breach escalation', function () {
    Notification::fake();

    $tz = config('tickets.timezone', 'Asia/Dhaka');
    $sundayMorning = CarbonImmutable::parse('2026-11-08 09:00:00', $tz);
    $this->travelTo($sundayMorning);

    $rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $admin = User::factory()->admin()->create(['name' => 'Active Admin']);
    $category = Category::factory()->create(['name' => 'Billing', 'is_active' => true]);

    $ticket = Ticket::factory()->create([
        'subject' => 'Breached ticket second run check',
        'priority' => TicketPriority::Critical,
        'status' => TicketStatus::InProgress,
        'category_id' => $category->id,
        'assigned_agent_id' => $rafi->id,
        'sla_policy_minutes' => 120,
        'sla_started_at' => $sundayMorning->setTimezone('UTC'),
        'sla_warning_at' => $sundayMorning->addMinutes(90)->setTimezone('UTC'),
        'sla_due_at' => $sundayMorning->addMinutes(120)->setTimezone('UTC'),
        'sla_warning_notified_at' => $sundayMorning->addMinutes(91)->setTimezone('UTC'),
    ]);

    $breachTime = CarbonImmutable::parse('2026-11-08 11:01:00', $tz);
    $this->travelTo($breachTime);
    $this->artisan('tickets:check-sla')->assertSuccessful();

    $initialActivitiesCount = $ticket->activities()->count();
    Notification::assertSentToTimes($rafi, \App\Notifications\Tickets\TicketEscalatedNotification::class, 1);

    // Second run a minute later
    $secondRun = CarbonImmutable::parse('2026-11-08 11:02:00', $tz);
    $this->travelTo($secondRun);
    $this->artisan('tickets:check-sla')->assertSuccessful();

    expect($ticket->activities()->count())->toBe($initialActivitiesCount);
    Notification::assertSentToTimes($rafi, \App\Notifications\Tickets\TicketEscalatedNotification::class, 1);
    Notification::assertSentToTimes($admin, \App\Notifications\Tickets\TicketEscalatedNotification::class, 1);
});

it('sends exactly one escalation email to an admin who held the ticket', function () {
    Notification::fake();

    $tz = config('tickets.timezone', 'Asia/Dhaka');
    $sundayMorning = CarbonImmutable::parse('2026-11-08 09:00:00', $tz);
    $this->travelTo($sundayMorning);

    $admin = User::factory()->admin()->create(['name' => 'Admin Holder']);
    $otherAdmin = User::factory()->admin()->create(['name' => 'Other Admin']);
    $category = Category::factory()->create(['name' => 'Billing', 'is_active' => true]);

    $ticket = Ticket::factory()->create([
        'subject' => 'Ticket held by admin',
        'priority' => TicketPriority::Critical,
        'status' => TicketStatus::InProgress,
        'category_id' => $category->id,
        'assigned_agent_id' => $admin->id,
        'lock_version' => 0,
        'sla_policy_minutes' => 120,
        'sla_started_at' => $sundayMorning->setTimezone('UTC'),
        'sla_warning_at' => $sundayMorning->addMinutes(90)->setTimezone('UTC'),
        'sla_due_at' => $sundayMorning->addMinutes(120)->setTimezone('UTC'),
        'sla_warning_notified_at' => $sundayMorning->addMinutes(91)->setTimezone('UTC'),
    ]);

    $breachTime = CarbonImmutable::parse('2026-11-08 11:01:00', $tz);
    $this->travelTo($breachTime);
    $this->artisan('tickets:check-sla')->assertSuccessful();

    Notification::assertSentToTimes($admin, \App\Notifications\Tickets\TicketEscalatedNotification::class, 1);
    Notification::assertSentToTimes($otherAdmin, \App\Notifications\Tickets\TicketEscalatedNotification::class, 1);
});

it('leaves escalated ticket unassigned on auto-assign retry, and agent claiming it preserves escalated_at', function () {
    Notification::fake();

    $tz = config('tickets.timezone', 'Asia/Dhaka');
    $sundayMorning = CarbonImmutable::parse('2026-11-08 09:00:00', $tz);
    $this->travelTo($sundayMorning);

    $mina = User::factory()->agent()->create(['name' => 'Mina', 'is_available' => true]);
    $category = Category::factory()->create(['name' => 'Billing', 'is_active' => true]);
    $mina->categories()->attach($category);

    $ticket = Ticket::factory()->create([
        'subject' => 'Escalated ticket awaiting claim',
        'priority' => TicketPriority::Critical,
        'status' => TicketStatus::Open,
        'category_id' => $category->id,
        'assigned_agent_id' => null,
        'first_assigned_at' => $sundayMorning->setTimezone('UTC'),
        'escalated_at' => $sundayMorning->addMinutes(121)->setTimezone('UTC'),
        'sla_breached_at' => $sundayMorning->addMinutes(121)->setTimezone('UTC'),
        'sla_warning_at' => $sundayMorning->addMinutes(90)->setTimezone('UTC'),
        'sla_due_at' => $sundayMorning->addMinutes(120)->setTimezone('UTC'),
        'lock_version' => 1,
    ]);

    // Run retry command with available agent Mina
    $this->artisan('tickets:assign-unassigned')->assertSuccessful();

    $ticket->refresh();
    expect($ticket->assigned_agent_id)->toBeNull();

    // Now Mina claims it
    /** @var \App\Services\Assignment\TicketAssignmentService $assignmentService */
    $assignmentService = app(\App\Services\Assignment\TicketAssignmentService::class);
    $claimed = $assignmentService->claim($ticket, $mina);

    expect($claimed->assigned_agent_id)->toBe($mina->id)
        ->and($claimed->escalated_at)->not->toBeNull();
});

it('escalates 450 breached tickets in one run via chunking', function () {
    Notification::fake();

    $tz = config('tickets.timezone', 'Asia/Dhaka');
    $sundayMorning = CarbonImmutable::parse('2026-11-08 09:00:00', $tz);
    $this->travelTo($sundayMorning);

    $customer = User::factory()->customer()->create();
    $category = Category::factory()->create(['name' => 'Billing', 'is_active' => true]);

    $nowUtc = $sundayMorning->setTimezone('UTC');
    $dueUtc = $sundayMorning->addMinutes(120)->setTimezone('UTC');
    $warningUtc = $sundayMorning->addMinutes(90)->setTimezone('UTC');

    $ticketsData = [];
    for ($i = 1; $i <= 450; $i++) {
        $ticketsData[] = [
            'ticket_number' => sprintf('TKT-2026-%06d', $i + 1000),
            'subject' => "Breached batch ticket {$i}",
            'description' => "Description {$i}",
            'priority' => 'critical',
            'status' => 'open',
            'category_id' => $category->id,
            'customer_id' => $customer->id,
            'created_by_id' => $customer->id,
            'assigned_agent_id' => null,
            'lock_version' => 0,
            'sla_policy_minutes' => 120,
            'sla_started_at' => $nowUtc,
            'sla_warning_at' => $warningUtc,
            'sla_due_at' => $dueUtc,
            'sla_warning_notified_at' => $warningUtc,
            'sla_breached_at' => null,
            'escalated_at' => null,
            'created_at' => $nowUtc,
            'updated_at' => $nowUtc,
        ];
    }

    Ticket::insert($ticketsData);

    $breachTime = CarbonImmutable::parse('2026-11-08 11:05:00', $tz);
    $this->travelTo($breachTime);

    $this->artisan('tickets:check-sla')->assertSuccessful();

    $escalatedCount = Ticket::query()
        ->whereNotNull('escalated_at')
        ->where('subject', 'like', 'Breached batch ticket %')
        ->count();

    expect($escalatedCount)->toBe(450);
});
