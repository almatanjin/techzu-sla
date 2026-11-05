<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active;
    }

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

    public function create(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function updateContent(User $user, Ticket $ticket): bool
    {
        if (! (bool) $user->is_active) {
            return false;
        }

        if ($user->isAdmin() || $user->isAgent()) {
            return $ticket->status !== TicketStatus::Closed;
        }

        if ($user->isCustomer() && $ticket->customer_id === $user->id) {
            return $ticket->status === TicketStatus::Open;
        }

        return false;
    }

    public function updateClassification(User $user, Ticket $ticket): bool
    {
        if (! (bool) $user->is_active) {
            return false;
        }

        if ($user->isAdmin() || $user->isAgent()) {
            return $ticket->status !== TicketStatus::Closed;
        }

        return false;
    }
}

