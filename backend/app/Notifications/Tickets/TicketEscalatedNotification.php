<?php

namespace App\Notifications\Tickets;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketEscalatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    public int $ticketId;

    public string $ticketNumber;

    public string $ticketSubject;

    public ?string $dueAt;

    public ?string $formerAssigneeName;

    public function __construct(Ticket $ticket, ?User $formerAssignee = null)
    {
        $this->ticketId = $ticket->id;
        $this->ticketNumber = $ticket->ticket_number;
        $this->ticketSubject = $ticket->subject;
        $tz = config('tickets.timezone', 'Asia/Dhaka');
        $this->dueAt = $ticket->sla_due_at?->timezone($tz)->format('Y-m-d H:i');
        $this->formerAssigneeName = $formerAssignee?->name;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lines = [];
        if ($this->dueAt !== null) {
            $lines[] = "Due at: {$this->dueAt}";
        }
        if ($this->formerAssigneeName !== null) {
            $lines[] = "Taken from {$this->formerAssigneeName}";
        }

        return TicketMail::for(
            ticket: $this->ticketNumber,
            subjectSuffix: 'Escalated: SLA deadline passed',
            lines: $lines,
            ticketSubject: $this->ticketSubject,
        );
    }
}
