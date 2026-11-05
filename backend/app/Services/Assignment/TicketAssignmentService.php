<?php

namespace App\Services\Assignment;

use App\Enums\TicketActivityType;
use App\Enums\TicketStatus;
use App\Events\TicketAssigned;
use App\Events\TicketNeedsAgent;
use App\Exceptions\TicketAlreadyAssignedException;
use App\Exceptions\TicketVersionConflictException;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketActivityLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TicketAssignmentService
{
    public function __construct(
        private readonly TicketActivityLogger $activityLogger,
        private readonly AgentCandidateRepository $candidateRepository,
        private readonly AssignmentStrategy $strategy,
    ) {}

    public function assign(Ticket $ticket, ?User $agent, int $lockVersion, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $agent, $lockVersion, $actor): Ticket {
            /** @var Ticket $fresh */
            $fresh = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if ($fresh->lock_version !== $lockVersion) {
                throw new TicketVersionConflictException($fresh);
            }

            if ($fresh->assigned_agent_id === $agent?->id) {
                return $fresh;
            }

            $oldAgent = $fresh->assignedAgent;

            $fresh->assigned_agent_id = $agent?->id;
            if ($fresh->first_assigned_at === null && $agent !== null) {
                $fresh->first_assigned_at = now();
            }
            $fresh->lock_version = $fresh->lock_version + 1;
            $fresh->save();

            if ($agent !== null) {
                $agent->last_assigned_at = now();
                $agent->save();
            }

            if ($agent !== null) {
                $this->activityLogger->log(
                    ticket: $fresh,
                    type: TicketActivityType::Assigned,
                    actor: $actor,
                    oldValues: $oldAgent ? ['agent' => ['id' => (int) $oldAgent->id, 'name' => (string) $oldAgent->name]] : null,
                    newValues: ['agent' => ['id' => (int) $agent->id, 'name' => (string) $agent->name]],
                );

                event(new TicketAssigned($fresh, $agent, actor: $actor));
            } else {
                $this->activityLogger->log(
                    ticket: $fresh,
                    type: TicketActivityType::Unassigned,
                    actor: $actor,
                    oldValues: $oldAgent ? ['agent' => ['id' => (int) $oldAgent->id, 'name' => (string) $oldAgent->name]] : null,
                    newValues: null,
                );
            }

            return $fresh;
        });
    }

    public function claim(Ticket $ticket, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor): Ticket {
            $affected = Ticket::query()
                ->whereKey($ticket->id)
                ->whereNull('assigned_agent_id')
                ->whereIn('status', array_map(fn ($s) => $s->value, TicketStatus::active()))
                ->update([
                    'assigned_agent_id' => $actor->id,
                    'first_assigned_at' => DB::raw('COALESCE(first_assigned_at, NOW())'),
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                /** @var Ticket $fresh */
                $fresh = Ticket::query()->whereKey($ticket->id)->firstOrFail();
                throw new TicketAlreadyAssignedException($fresh);
            }

            /** @var Ticket $fresh */
            $fresh = Ticket::query()->whereKey($ticket->id)->firstOrFail();

            $actor->last_assigned_at = now();
            $actor->save();

            $this->activityLogger->log(
                ticket: $fresh,
                type: TicketActivityType::Claimed,
                actor: $actor,
                oldValues: null,
                newValues: ['agent' => ['id' => (int) $actor->id, 'name' => (string) $actor->name]],
            );

            return $fresh;
        });
    }

    public function release(Ticket $ticket, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor): Ticket {
            $affected = Ticket::query()
                ->whereKey($ticket->id)
                ->where('assigned_agent_id', $actor->id)
                ->whereIn('status', array_map(fn ($s) => $s->value, TicketStatus::active()))
                ->update([
                    'assigned_agent_id' => null,
                    'lock_version' => DB::raw('lock_version + 1'),
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                /** @var Ticket $fresh */
                $fresh = Ticket::query()->whereKey($ticket->id)->firstOrFail();
                throw new TicketVersionConflictException($fresh);
            }

            /** @var Ticket $fresh */
            $fresh = Ticket::query()->whereKey($ticket->id)->firstOrFail();

            $this->activityLogger->log(
                ticket: $fresh,
                type: TicketActivityType::Unassigned,
                actor: $actor,
                oldValues: ['agent' => ['id' => (int) $actor->id, 'name' => (string) $actor->name]],
                newValues: null,
            );

            return $fresh;
        });
    }

    public function autoAssign(Ticket $ticket, bool $notifyWhenNoAgent = false): ?Ticket
    {
        return Cache::lock('tickets:auto-assign', 10)->block(5, function () use ($ticket, $notifyWhenNoAgent): ?Ticket {
            $fresh = Ticket::query()->find($ticket->id);
            if (! $fresh || $fresh->assigned_agent_id !== null || ! in_array($fresh->status, TicketStatus::active(), true)) {
                return null;
            }

            $candidates = $this->candidateRepository->available();
            $pickedId = $this->strategy->pick((int) $fresh->category_id, $candidates);

            if ($pickedId === null) {
                if ($notifyWhenNoAgent) {
                    event(new TicketNeedsAgent($fresh));
                }

                return null;
            }

            return DB::transaction(function () use ($fresh, $pickedId): ?Ticket {
                $affected = Ticket::query()
                    ->whereKey($fresh->id)
                    ->whereNull('assigned_agent_id')
                    ->whereIn('status', array_map(fn ($s) => $s->value, TicketStatus::active()))
                    ->update([
                        'assigned_agent_id' => $pickedId,
                        'first_assigned_at' => DB::raw('COALESCE(first_assigned_at, NOW())'),
                        'lock_version' => DB::raw('lock_version + 1'),
                        'updated_at' => now(),
                    ]);

                if ($affected === 0) {
                    return null;
                }

                $pickedAgent = User::query()->find($pickedId);
                if ($pickedAgent) {
                    $pickedAgent->last_assigned_at = now();
                    $pickedAgent->save();
                }

                /** @var Ticket $updated */
                $updated = Ticket::query()->whereKey($fresh->id)->firstOrFail();

                $this->activityLogger->log(
                    ticket: $updated,
                    type: TicketActivityType::Assigned,
                    actor: null,
                    oldValues: null,
                    newValues: ['agent' => ['id' => (int) $pickedAgent->id, 'name' => (string) $pickedAgent->name]],
                );

                event(new TicketAssigned($updated, $pickedAgent, actor: null));

                return $updated;
            });
        });
    }
}
