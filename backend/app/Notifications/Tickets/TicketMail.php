<?php

namespace App\Notifications\Tickets;

use App\Models\Ticket;
use Illuminate\Notifications\Messages\MailMessage;

class TicketMail
{
    /**
     * @param  list<string>  $lines
     */
    public static function for(Ticket|string $ticket, string $subjectSuffix, array $lines = [], ?string $ticketSubject = null): MailMessage
    {
        if ($ticket instanceof Ticket) {
            $ticketNumber = $ticket->ticket_number;
            $subject = $ticket->subject;
        } else {
            $ticketNumber = $ticket;
            $subject = $ticketSubject ?? '';
        }

        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');

        $mail = (new MailMessage)
            ->subject("[{$ticketNumber}] {$subjectSuffix}");

        if (! empty($subject)) {
            $mail->line("Ticket: {$subject}");
        }

        foreach ($lines as $line) {
            $mail->line($line);
        }

        return $mail->action('View ticket', "{$frontendUrl}/tickets/{$ticketNumber}");
    }
}

