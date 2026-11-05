<?php

namespace App\Http\Resources;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketWorkflow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
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
            'description' => $this->description,
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
            'created_by' => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null,
            'assigned_agent' => $this->assignedAgent ? [
                'id' => $this->assignedAgent->id,
                'name' => $this->assignedAgent->name,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'lock_version' => (int) $this->lock_version,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments', fn () => $this->attachments, [])),
            'sla' => $this->when($viewerIsStaff, fn () => [
                'status' => $this->slaStatus()->value,
                'started_at' => $this->sla_started_at?->toISOString(),
                'warning_at' => $this->sla_warning_at?->toISOString(),
                'due_at' => $this->sla_due_at?->toISOString(),
                'policy_minutes' => (int) $this->sla_policy_minutes,
                'escalated_at' => $this->escalated_at?->toISOString(),
            ]),
            'permissions' => [
                'can_edit_content' => $this->canUpdateContent($user),
                'can_edit_classification' => $this->canUpdateClassification($user),
                'can_update_content' => $this->canUpdateContent($user),
                'can_update_classification' => $this->canUpdateClassification($user),
                'allowed_statuses' => $this->allowedStatuses($user),
                'can_assign' => $user !== null && $user->can('assign', $this->resource),
                'can_claim' => $user !== null && $this->assigned_agent_id === null && $user->can('claim', $this->resource),
                'can_release' => $user !== null && $user->can('release', $this->resource),
                'can_reply' => $user !== null && app(\App\Policies\TicketMessagePolicy::class)->canReply($user, $this->resource),
                'can_add_internal_note' => $user !== null && app(\App\Policies\TicketMessagePolicy::class)->canAddInternalNote($user, $this->resource),
            ],
        ];
    }

    private function canUpdateContent(?User $user): bool
    {
        if ($user === null || ! $user->is_active) {
            return false;
        }

        if ($user->isAdmin() || $user->isAgent()) {
            return $this->status !== TicketStatus::Closed;
        }

        if ($user->isCustomer() && $this->customer_id === $user->id) {
            return $this->status === TicketStatus::Open;
        }

        return false;
    }

    private function canUpdateClassification(?User $user): bool
    {
        if ($user === null || ! $user->is_active) {
            return false;
        }

        if ($user->isAdmin() || $user->isAgent()) {
            return $this->status !== TicketStatus::Closed;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function allowedStatuses(?User $user): array
    {
        $workflow = app(TicketWorkflow::class);
        $targets = $workflow->allowedTargets($this->resource, $user);

        return array_map(fn (TicketStatus $s) => $s->value, $targets);
    }
}
