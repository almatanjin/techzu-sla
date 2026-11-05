<?php

use App\Enums\TicketMessageType;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\Tickets\CustomerRepliedNotification;
use App\Notifications\Tickets\InternalNoteAddedNotification;
use App\Notifications\Tickets\SlaWarningNotification;
use App\Notifications\Tickets\StaffRepliedNotification;
use App\Notifications\Tickets\TicketAssignedNotification;
use App\Notifications\Tickets\TicketCreatedNotification;
use App\Notifications\Tickets\TicketEscalatedNotification;
use App\Notifications\Tickets\TicketNeedsAgentNotification;
use App\Notifications\Tickets\TicketResolvedNotification;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->category = Category::factory()->create(['name' => 'Support']);
    $this->ana = User::factory()->customer()->create(['name' => 'Ana']);
    $this->rafi = User::factory()->agent()->create(['name' => 'Rafi']);
    $this->admin = User::factory()->admin()->create(['name' => 'Admin']);

    $tz = config('tickets.timezone', 'Asia/Dhaka');
    $this->sundayMorning = CarbonImmutable::parse('2026-11-08 09:00:00', $tz);

    $this->ticket = Ticket::factory()->create([
        'ticket_number' => 'TKT-2026-000001',
        'subject' => 'Cannot access invoice PDF',
        'category_id' => $this->category->id,
        'customer_id' => $this->ana->id,
        'assigned_agent_id' => $this->rafi->id,
        'sla_policy_minutes' => 120,
        'sla_started_at' => $this->sundayMorning->setTimezone('UTC'),
        'sla_warning_at' => $this->sundayMorning->addMinutes(90)->setTimezone('UTC'),
        'sla_due_at' => $this->sundayMorning->addMinutes(120)->setTimezone('UTC'),
    ]);
});

it('renders StaffRepliedNotification with expected subject, ticket subject, reply text, and action button', function () {
    $message = new TicketMessage([
        'ticket_id' => $this->ticket->id,
        'author_id' => $this->rafi->id,
        'type' => TicketMessageType::Reply,
        'body' => 'I have regenerated your invoice and emailed a copy.',
    ]);
    $message->save();

    $notification = new StaffRepliedNotification($this->ticket, $message);
    $mail = $notification->toMail($this->ana);

    expect($mail->subject)->toBe('[TKT-2026-000001] New reply from support')
        ->and($mail->introLines)->toContain('Ticket: Cannot access invoice PDF')
        ->and($mail->introLines)->toContain('I have regenerated your invoice and emailed a copy.')
        ->and($mail->actionText)->toBe('View ticket')
        ->and($mail->actionUrl)->toBe(config('app.frontend_url', 'http://localhost:5173').'/tickets/TKT-2026-000001');

    $html = (string) $mail->render();
    expect($html)->toContain('Cannot access invoice PDF')
        ->and($html)->toContain('I have regenerated your invoice and emailed a copy.')
        ->and($html)->toContain('/tickets/TKT-2026-000001');
});

it('renders correct subjects for all notification classes', function () {
    $replyMessage = new TicketMessage([
        'ticket_id' => $this->ticket->id,
        'author_id' => $this->ana->id,
        'type' => TicketMessageType::Reply,
        'body' => 'Here is my reply.',
    ]);
    $replyMessage->save();

    $noteMessage = new TicketMessage([
        'ticket_id' => $this->ticket->id,
        'author_id' => $this->rafi->id,
        'type' => TicketMessageType::InternalNote,
        'body' => 'Internal investigation note.',
    ]);
    $noteMessage->save();

    expect((new TicketCreatedNotification($this->ticket))->toMail($this->ana)->subject)
        ->toBe('[TKT-2026-000001] We received your request')
        ->and((new TicketNeedsAgentNotification($this->ticket))->toMail($this->admin)->subject)
        ->toBe('[TKT-2026-000001] New ticket needs an agent')
        ->and((new TicketAssignedNotification($this->ticket))->toMail($this->rafi)->subject)
        ->toBe('[TKT-2026-000001] Ticket assigned to you')
        ->and((new CustomerRepliedNotification($this->ticket, $replyMessage))->toMail($this->rafi)->subject)
        ->toBe('[TKT-2026-000001] Customer replied')
        ->and((new InternalNoteAddedNotification($this->ticket, $noteMessage))->toMail($this->rafi)->subject)
        ->toBe('[TKT-2026-000001] New internal note')
        ->and((new TicketResolvedNotification($this->ticket))->toMail($this->ana)->subject)
        ->toBe('[TKT-2026-000001] Your ticket was resolved')
        ->and((new SlaWarningNotification($this->ticket))->toMail($this->rafi)->subject)
        ->toBe('[TKT-2026-000001] SLA deadline approaching')
        ->and((new TicketEscalatedNotification($this->ticket, $this->rafi))->toMail($this->admin)->subject)
        ->toBe('[TKT-2026-000001] Escalated: SLA deadline passed');
});

it('renders escalation mail with due time in Asia/Dhaka and taken from Rafi', function () {
    $notification = new TicketEscalatedNotification($this->ticket, $this->rafi);
    $mail = $notification->toMail($this->admin);

    expect($mail->introLines)->toContain('Due at: 2026-11-08 11:00')
        ->and($mail->introLines)->toContain('Taken from Rafi');

    $html = (string) $mail->render();
    expect($html)->toContain('2026-11-08 11:00')
        ->and($html)->toMatch('/taken from Rafi/i');
});

it('renders SLA warning mail with due time in Asia/Dhaka', function () {
    $notification = new SlaWarningNotification($this->ticket);
    $mail = $notification->toMail($this->rafi);

    expect($mail->introLines)->toContain('Due at: 2026-11-08 11:00');

    $html = (string) $mail->render();
    expect($html)->toContain('2026-11-08 11:00');
});

it('omits SLA data and internal note text from customer-facing emails', function () {
    $replyMessage = new TicketMessage([
        'ticket_id' => $this->ticket->id,
        'author_id' => $this->rafi->id,
        'type' => TicketMessageType::Reply,
        'body' => 'Public customer reply message.',
    ]);
    $replyMessage->save();

    $createdMail = (new TicketCreatedNotification($this->ticket))->toMail($this->ana);
    $staffReplyMail = (new StaffRepliedNotification($this->ticket, $replyMessage))->toMail($this->ana);
    $resolvedMail = (new TicketResolvedNotification($this->ticket))->toMail($this->ana);

    foreach ([$createdMail, $staffReplyMail, $resolvedMail] as $mail) {
        $content = implode(' ', $mail->introLines);
        expect($content)->not->toContain('Due at')
            ->and($content)->not->toContain('2026-11-08 11:00')
            ->and($content)->not->toMatch('/breached|escalat|warning/i')
            ->and($content)->not->toContain('internal_note')
            ->and($content)->not->toContain('internal note');
    }
});

it('renders internal note mail containing note body text', function () {
    $noteMessage = new TicketMessage([
        'ticket_id' => $this->ticket->id,
        'author_id' => $this->admin->id,
        'type' => TicketMessageType::InternalNote,
        'body' => 'Secret customer credit investigation completed.',
    ]);
    $noteMessage->save();

    $mail = (new InternalNoteAddedNotification($this->ticket, $noteMessage))->toMail($this->rafi);
    expect($mail->introLines)->toContain('Secret customer credit investigation completed.');

    $html = (string) $mail->render();
    expect($html)->toContain('Secret customer credit investigation completed.');
});
