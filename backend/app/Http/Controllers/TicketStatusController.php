<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeStatusRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\Tickets\TicketService;

class TicketStatusController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
    ) {}

    public function __invoke(ChangeStatusRequest $request, Ticket $ticket): TicketResource
    {
        $updatedTicket = $this->ticketService->changeStatus(
            $ticket,
            $request->toData(),
            $request->user()
        );

        return new TicketResource($updatedTicket);
    }
}

