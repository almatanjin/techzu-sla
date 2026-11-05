<?php

namespace App\Services\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class TicketWorkflow
{
    /**
     * @return list<TicketStatus>
     */
    public function allowedTargets(Ticket $ticket, ?User $user): array
    {
        if ($user === null || ! $user->is_active) {
            return [];
        }

        $current = $ticket->status;
        if ($current === TicketStatus::Closed) {
            return [];
        }

        $isStaff = $user->isAdmin() || $user->isAgent();
        $isOwner = $user->isCustomer() && $ticket->customer_id === $user->id;

        if ($isStaff) {
            return match ($current) {
                TicketStatus::Open => [
                    TicketStatus::InProgress,
                    TicketStatus::WaitingForCustomer,
                    TicketStatus::Resolved,
                    TicketStatus::Closed,
                ],
                TicketStatus::InProgress => [
                    TicketStatus::WaitingForCustomer,
                    TicketStatus::Resolved,
                    TicketStatus::Closed,
                ],
                TicketStatus::WaitingForCustomer => [
                    TicketStatus::InProgress,
                    TicketStatus::Resolved,
                    TicketStatus::Closed,
                ],
                TicketStatus::Resolved => [
                    TicketStatus::InProgress,
                    TicketStatus::Closed,
                ],
                default => [],
            };
        }

        if ($isOwner) {
            return match ($current) {
                TicketStatus::Open,
                TicketStatus::InProgress,
                TicketStatus::WaitingForCustomer => [
                    TicketStatus::Closed,
                ],
                TicketStatus::Resolved => [
                    TicketStatus::Open,
                    TicketStatus::Closed,
                ],
                default => [],
            };
        }

        return [];
    }

    /**
     * Check if a transition is valid in the state machine by ANY actor.
     */
    public function isValidTransition(TicketStatus $from, TicketStatus $to): bool
    {
        if ($from === TicketStatus::Closed) {
            return false;
        }

        return match ($from) {
            TicketStatus::Open => in_array($to, [
                TicketStatus::InProgress,
                TicketStatus::WaitingForCustomer,
                TicketStatus::Resolved,
                TicketStatus::Closed,
            ], true),
            TicketStatus::InProgress => in_array($to, [
                TicketStatus::WaitingForCustomer,
                TicketStatus::Resolved,
                TicketStatus::Closed,
            ], true),
            TicketStatus::WaitingForCustomer => in_array($to, [
                TicketStatus::InProgress,
                TicketStatus::Resolved,
                TicketStatus::Closed,
            ], true),
            TicketStatus::Resolved => in_array($to, [
                TicketStatus::Open,
                TicketStatus::InProgress,
                TicketStatus::Closed,
            ], true),
            default => false,
        };
    }

    public function assertCanTransition(Ticket $ticket, User $user, TicketStatus $target): void
    {
        if (! $this->isValidTransition($ticket->status, $target)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition ticket from {$ticket->status->value} to {$target->value}."],
            ]);
        }

        $allowed = $this->allowedTargets($ticket, $user);
        if (! in_array($target, $allowed, true)) {
            throw new AuthorizationException("You are not authorized to transition this ticket to {$target->value}.");
        }
    }
}

