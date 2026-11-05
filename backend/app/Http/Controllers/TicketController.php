<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListTicketsRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\TicketListResource;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\Tickets\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
    ) {}

    public function index(ListTicketsRequest $request): AnonymousResourceCollection
    {
        $tickets = $this->ticketService->list(
            $request->user(),
            $request->input('view', 'active')
        );

        return TicketListResource::collection($tickets);
    }

    public function show(Request $request, Ticket $ticket): TicketResource
    {
        Gate::authorize('view', $ticket);

        $ticket->loadMissing([
            'category:id,name',
            'customer:id,name,email',
            'createdBy:id,name',
            'assignedAgent:id,name',
        ]);

        return new TicketResource($ticket);
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->create(
            $request->toData(),
            $request->user()
        );

        return (new TicketResource($ticket))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): TicketResource
    {
        $updatedTicket = $this->ticketService->update(
            $ticket,
            $request->toData(),
            $request->user()
        );

        return new TicketResource($updatedTicket);
    }
}
