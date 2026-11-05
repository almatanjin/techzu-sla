<?php

namespace App\Exceptions;

use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketVersionConflictException extends Exception
{
    public function __construct(
        public readonly Ticket $currentTicket,
        string $message = 'This ticket was changed by someone else. The latest version is now shown.'
    ) {
        parent::__construct($message);
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

