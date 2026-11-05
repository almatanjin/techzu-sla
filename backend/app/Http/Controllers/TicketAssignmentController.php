<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Assignment\TicketAssignmentService;

class TicketAssignmentController extends Controller
{
    public function update(
        AssignTicketRequest $request,
        Ticket $ticket,
        TicketAssignmentService $service
    ): TicketResource {
        $agent = $request->input('agent_id') !== null
            ? User::query()->findOrFail($request->input('agent_id'))
            : null;

        $updatedTicket = $service->assign(
            ticket: $ticket,
            agent: $agent,
            lockVersion: (int) $request->input('lock_version'),
            actor: $request->user(),
        );

        $updatedTicket->load([
            'category:id,name',
            'customer:id,name,email',
            'createdBy:id,name',
            'assignedAgent:id,name',
        ]);

        return new TicketResource($updatedTicket);
    }
}
