<?php

namespace App\Services\Sla;

use App\Enums\TicketActivityType;
use App\Enums\TicketStatus;
use App\Events\TicketEscalated;
use App\Events\TicketSlaWarningReached;
use App\Models\Ticket;
use App\Services\Tickets\TicketActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SlaEscalationService
{
    public function __construct(
        private readonly TicketActivityLogger $activityLogger,
    ) {}

    public function run(CarbonImmutable $now): SlaCheckResult
    {
        $activeStatuses = array_map(fn ($s) => $s->value, TicketStatus::active());
        $warnedCount = 0;
        $escalatedCount = 0;

        // 1. Warnings first
        Ticket::query()
            ->whereIn('status', $activeStatuses)
            ->where('sla_warning_at', '<=', $now)
            ->whereNull('sla_warning_notified_at')
            ->select('id')
            ->chunkById(200, function ($tickets) use (&$warnedCount, $now, $activeStatuses) {
                foreach ($tickets as $ticketRow) {
                    $id = $ticketRow->id;
                    $handled = DB::transaction(function () use ($id, $now, $activeStatuses): bool {
                        $claimed = Ticket::query()
                            ->whereKey($id)
                            ->whereNull('sla_warning_notified_at')
                            ->where('sla_warning_at', '<=', $now)
                            ->whereIn('status', $activeStatuses)
                            ->update(['sla_warning_notified_at' => $now]);

                        if ($claimed === 0) {
                            return false;
                        }

                        /** @var Ticket $ticket */
                        $ticket = Ticket::query()->with(['assignedAgent', 'customer'])->findOrFail($id);

                        $this->activityLogger->log(
                            ticket: $ticket,
                            type: TicketActivityType::SlaWarning,
                            actor: null,
                            oldValues: null,
                            newValues: ['due_at' => $ticket->sla_due_at?->toISOString()],
                        );

                        event(new TicketSlaWarningReached($ticket));

                        return true;
                    });

                    if ($handled) {
                        $warnedCount++;
                    }
                }
            });

        // 2. Breaches
        Ticket::query()
            ->whereIn('status', $activeStatuses)
            ->where('sla_due_at', '<=', $now)
            ->whereNull('sla_breached_at')
            ->select('id')
            ->chunkById(200, function ($tickets) use (&$escalatedCount, $now, $activeStatuses) {
                foreach ($tickets as $ticketRow) {
                    $id = $ticketRow->id;
                    $handled = DB::transaction(function () use ($id, $now, $activeStatuses): bool {
                        $claimed = Ticket::query()
                            ->whereKey($id)
                            ->whereNull('sla_breached_at')
                            ->where('sla_due_at', '<=', $now)
                            ->whereIn('status', $activeStatuses)
                            ->update(['sla_breached_at' => $now]);

                        if ($claimed === 0) {
                            return false;
                        }

                        /** @var Ticket $fresh */
                        $fresh = Ticket::query()
                            ->with(['assignedAgent', 'customer'])
                            ->lockForUpdate()
                            ->findOrFail($id);

                        $formerAssignee = $fresh->assignedAgent;

                        $fresh->assigned_agent_id = null;
                        $fresh->escalated_at = $now;
                        $fresh->lock_version = $fresh->lock_version + 1;
                        $fresh->save();

                        if ($formerAssignee !== null) {
                            $this->activityLogger->log(
                                ticket: $fresh,
                                type: TicketActivityType::Unassigned,
                                actor: null,
                                oldValues: [
                                    'agent' => [
                                        'id' => (int) $formerAssignee->id,
                                        'name' => (string) $formerAssignee->name,
                                    ],
                                ],
                                newValues: null,
                            );
                        }

                        $this->activityLogger->log(
                            ticket: $fresh,
                            type: TicketActivityType::Escalated,
                            actor: null,
                            oldValues: null,
                            newValues: ['due_at' => $fresh->sla_due_at?->toISOString()],
                        );

                        event(new TicketEscalated($fresh, $formerAssignee));

                        return true;
                    });

                    if ($handled) {
                        $escalatedCount++;
                    }
                }
            });

        return new SlaCheckResult(
            warnedCount: $warnedCount,
            escalatedCount: $escalatedCount,
        );
    }
}
