<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\Assignment\TicketAssignmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AutoAssignTicket implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public int $ticketId
    ) {}

    public function handle(TicketAssignmentService $service): void
    {
        $ticket = Ticket::query()->find($this->ticketId);
        if ($ticket) {
            $service->autoAssign($ticket, notifyWhenNoAgent: true);
        }
    }
}

