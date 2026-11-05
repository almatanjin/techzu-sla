<?php

namespace App\Policies;

use App\Enums\TicketMessageType;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TicketMessagePolicy
{
    public function view(User $user, Ticket $ticket): Response
    {
        if (! (bool) $user->is_active) {
            return Response::deny();
        }

        if ($user->isAdmin() || $user->isAgent()) {
            return Response::allow();
        }

        if ($user->isCustomer() && $ticket->customer_id === $user->id) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function create(User $user, Ticket $ticket, TicketMessageType $type = TicketMessageType::Reply): Response
    {
        $viewResponse = $this->view($user, $ticket);
        if ($viewResponse->denied()) {
            return $viewResponse;
        }

        if ($ticket->status === TicketStatus::Closed) {
            return Response::deny('Closed tickets accept no new messages.');
        }

        if ($type === TicketMessageType::InternalNote && ! ($user->isAdmin() || $user->isAgent())) {
            return Response::deny('Only support staff may add internal notes.');
        }

        return Response::allow();
    }

    public function canReply(User $user, Ticket $ticket): bool
    {
        return $this->create($user, $ticket, TicketMessageType::Reply)->allowed();
    }

    public function canAddInternalNote(User $user, Ticket $ticket): bool
    {
        return $this->create($user, $ticket, TicketMessageType::InternalNote)->allowed();
    }
}

