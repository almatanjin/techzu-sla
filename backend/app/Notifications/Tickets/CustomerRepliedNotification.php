<?php

namespace App\Notifications\Tickets;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerRepliedNotification extends Notification implements ShouldQueue
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

    public string $replyText;

    public function __construct(Ticket $ticket, TicketMessage $message)
    {
        $this->ticketId = $ticket->id;
        $this->ticketNumber = $ticket->ticket_number;
        $this->ticketSubject = $ticket->subject;
        $this->replyText = (string) $message->body;
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
        return TicketMail::for(
            ticket: $this->ticketNumber,
            subjectSuffix: 'Customer replied',
            lines: [$this->replyText],
            ticketSubject: $this->ticketSubject,
        );
    }
}
