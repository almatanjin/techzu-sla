<?php

namespace App\Console\Commands;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Services\Assignment\TicketAssignmentService;
use Illuminate\Console\Command;

class AssignUnassignedTicketsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:assign-unassigned';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry automatic assignment for never-assigned active tickets';

    /**
     * Execute the console command.
     */
    public function handle(TicketAssignmentService $service): int
    {
        $activeStatuses = array_map(fn ($s) => $s->value, TicketStatus::active());

        Ticket::query()
            ->whereIn('status', $activeStatuses)
            ->whereNull('assigned_agent_id')
            ->whereNull('first_assigned_at')
            ->whereNull('escalated_at')
            ->whereNull('ticket_import_id')
            ->orderBy('id')
            ->chunkById(100, function ($tickets) use ($service) {
                foreach ($tickets as $ticket) {
                    $service->autoAssign($ticket);
                }
            });

        return self::SUCCESS;
    }
}

