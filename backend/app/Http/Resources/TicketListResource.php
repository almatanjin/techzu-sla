<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $viewerIsStaff = $user !== null && ($user->isAdmin() || $user->isAgent());

        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'subject' => $this->subject,
            'priority' => $this->priority instanceof \BackedEnum ? $this->priority->value : $this->priority,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null,
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
            ] : null,
            'assigned_agent' => $this->assignedAgent ? [
                'id' => $this->assignedAgent->id,
                'name' => $this->assignedAgent->name,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'sla' => $this->when($viewerIsStaff, fn () => [
                'status' => $this->slaStatus()->value,
                'started_at' => $this->sla_started_at?->toISOString(),
                'warning_at' => $this->sla_warning_at?->toISOString(),
                'due_at' => $this->sla_due_at?->toISOString(),
                'policy_minutes' => (int) $this->sla_policy_minutes,
            ]),
        ];
    }
}
