<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Tickets\SlaWarningNotification;
use App\Notifications\Tickets\TicketEscalatedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->agent = User::factory()->agent()->create();
    $this->agentToken = $this->agent->createToken('agent-token')->plainTextToken;
    $this->category = Category::factory()->create(['name' => 'Support', 'is_active' => true]);
});

it('clears sla_warning_notified_at when lowering priority moves warning to the future, and re-arms warning', function () {
    Notification::fake();

    $tz = config('tickets.timezone', 'Asia/Dhaka');
    $sundayMorning = CarbonImmutable::parse('2026-11-08 09:00:00', $tz);
    $this->travelTo($sundayMorning);

    // High priority: 4 hours (240 min) resolution, warning at 180 min (12:00)
    $ticket = Ticket::factory()->create([
        'subject' => 'High priority issue',
        'priority' => TicketPriority::High,
        'status' => TicketStatus::InProgress,
        'category_id' => $this->category->id,
        'assigned_agent_id' => $this->agent->id,
        'lock_version' => 0,
        'sla_policy_minutes' => 240,
        'sla_started_at' => $sundayMorning->setTimezone('UTC'),
        'sla_warning_at' => $sundayMorning->addMinutes(180)->setTimezone('UTC'), // 12:00
        'sla_due_at' => $sundayMorning->addMinutes(240)->setTimezone('UTC'), // 13:00
    ]);

    // Travel past warning time to 12:01
    $warningPassed = CarbonImmutable::parse('2026-11-08 12:01:00', $tz);
    $this->travelTo($warningPassed);
    $this->artisan('tickets:check-sla')->assertSuccessful();

    $ticket->refresh();
    expect($ticket->sla_warning_notified_at)->not->toBeNull();
    Notification::assertSentToTimes($this->agent, SlaWarningNotification::class, 1);

    // Agent lowers priority to Low through PATCH /api/v1/tickets/{number}
    // Low priority resolution is 24 business hours, moving warning time to days later
    $patchResponse = $this->withToken($this->agentToken)
        ->patchJson("/api/v1/tickets/{$ticket->ticket_number}", [
            'priority' => 'low',
            'lock_version' => $ticket->lock_version,
        ]);

    $patchResponse->assertOk();

    $ticket->refresh();
    expect($ticket->sla_warning_notified_at)->toBeNull();

    // Check again at 12:05 - should NOT warn
    $this->travelTo($warningPassed->addMinutes(4));
    $this->artisan('tickets:check-sla')->assertSuccessful();
    Notification::assertSentToTimes($this->agent, SlaWarningNotification::class, 1);

    // Travel past the new warning time
    $pastNewWarning = $ticket->sla_warning_at->timezone($tz)->addMinute();
    $this->travelTo($pastNewWarning);
    $this->artisan('tickets:check-sla')->assertSuccessful();

    $ticket->refresh();
    expect($ticket->sla_warning_notified_at)->not->toBeNull();
    Notification::assertSentToTimes($this->agent, SlaWarningNotification::class, 2);
});

it('keeps escalated_at when due time moves to the future, and escalates again after new due time', function () {
    Notification::fake();

    $tz = config('tickets.timezone', 'Asia/Dhaka');
    $sundayMorning = CarbonImmutable::parse('2026-11-08 09:00:00', $tz);
    $this->travelTo($sundayMorning);

    $admin = User::factory()->admin()->create();

    // Critical ticket, due at 11:00
    $ticket = Ticket::factory()->create([
        'subject' => 'Critical issue to breach',
        'priority' => TicketPriority::Critical,
        'status' => TicketStatus::InProgress,
        'category_id' => $this->category->id,
        'assigned_agent_id' => $this->agent->id,
        'lock_version' => 0,
        'sla_policy_minutes' => 120,
        'sla_started_at' => $sundayMorning->setTimezone('UTC'),
        'sla_warning_at' => $sundayMorning->addMinutes(90)->setTimezone('UTC'),
        'sla_due_at' => $sundayMorning->addMinutes(120)->setTimezone('UTC'),
        'sla_warning_notified_at' => $sundayMorning->addMinutes(91)->setTimezone('UTC'),
    ]);

    // Breach at 11:01
    $breachTime = CarbonImmutable::parse('2026-11-08 11:01:00', $tz);
    $this->travelTo($breachTime);
    $this->artisan('tickets:check-sla')->assertSuccessful();

    $ticket->refresh();
    $firstEscalatedAt = $ticket->escalated_at;
    expect($firstEscalatedAt)->not->toBeNull()
        ->and($ticket->sla_breached_at)->not->toBeNull();

    // Staff changes priority to Low (due time moves to several business days later)
    $patchResponse = $this->withToken($this->agentToken)
        ->patchJson("/api/v1/tickets/{$ticket->ticket_number}", [
            'priority' => 'low',
            'lock_version' => $ticket->lock_version,
        ]);

    $patchResponse->assertOk();

    $ticket->refresh();
    // escalated_at is preserved, but sla_breached_at is cleared because new due time is in the future
    expect($ticket->escalated_at->toISOString())->toBe($firstEscalatedAt->toISOString())
        ->and($ticket->sla_breached_at)->toBeNull();

    // Check at 11:05 - should NOT escalate again yet
    $this->travelTo($breachTime->addMinutes(4));
    $this->artisan('tickets:check-sla')->assertSuccessful();
    Notification::assertSentToTimes($admin, TicketEscalatedNotification::class, 1);

    // Reassign agent to test second escalation unassignment
    $ticket->assigned_agent_id = $this->agent->id;
    $ticket->save();

    // Travel past new due time
    $pastNewDue = $ticket->sla_due_at->timezone($tz)->addMinute();
    $this->travelTo($pastNewDue);
    $this->artisan('tickets:check-sla')->assertSuccessful();

    $ticket->refresh();
    expect($ticket->sla_breached_at)->not->toBeNull()
        ->and($ticket->escalated_at->toISOString())->not->toBe($firstEscalatedAt->toISOString());

    Notification::assertSentToTimes($admin, TicketEscalatedNotification::class, 2);
});
