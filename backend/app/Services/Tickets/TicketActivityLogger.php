<?php

namespace App\Services\Tickets;

use App\Enums\TicketActivityType;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;

class TicketActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        Ticket $ticket,
        TicketActivityType $type,
        ?User $actor = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): TicketActivity {
        $activity = new TicketActivity([
            'ticket_id' => $ticket->id,
            'actor_id' => $actor?->id,
            'type' => $type,
            'old_values' => ! empty($oldValues) ? $oldValues : null,
            'new_values' => ! empty($newValues) ? $newValues : null,
            'created_at' => now(),
        ]);
        $activity->save();

        return $activity;
    }
}

