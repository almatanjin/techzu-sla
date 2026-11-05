<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\Assignment\TicketAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TicketReleaseController extends Controller
{
    public function __invoke(
        Request $request,
        Ticket $ticket,
        TicketAssignmentService $service
    ): TicketResource {
        Gate::authorize('release', $ticket);

        $releasedTicket = $service->release($ticket, $request->user());

        $releasedTicket->load([
            'category:id,name',
            'customer:id,name,email',
            'createdBy:id,name',
            'assignedAgent:id,name',
        ]);

        return new TicketResource($releasedTicket);
    }
}

