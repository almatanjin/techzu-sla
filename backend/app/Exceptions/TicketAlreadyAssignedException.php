<?php

namespace App\Exceptions;

use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketAlreadyAssignedException extends Exception
{
    public function __construct(
        public readonly Ticket $currentTicket
    ) {
        $this->currentTicket->loadMissing('assignedAgent:id,name');
        $name = $this->currentTicket->assignedAgent?->name ?? 'another agent';
        parent::__construct("This ticket was already taken by {$name}.");
    }

    public function render(Request $request): JsonResponse
    {
        $this->currentTicket->loadMissing([
            'category:id,name',
            'customer:id,name,email',
            'createdBy:id,name',
            'assignedAgent:id,name',
        ]);

        return response()->json([
            'message' => $this->getMessage(),
            'ticket' => (new TicketResource($this->currentTicket))->resolve($request),
        ], 409);
    }
}

