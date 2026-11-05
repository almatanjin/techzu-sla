<?php

namespace App\Services\Assignment;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class AgentCandidateRepository
{
    /**
     * @return array<int, int> [agent_id => active_ticket_count]
     */
    public function getActiveTicketCounts(): array
    {
        $activeStatuses = array_map(fn ($s) => $s->value, TicketStatus::active());

        return Ticket::query()
            ->selectRaw('assigned_agent_id, COUNT(*) as count')
            ->whereNotNull('assigned_agent_id')
            ->whereIn('status', $activeStatuses)
            ->groupBy('assigned_agent_id')
            ->pluck('count', 'assigned_agent_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Active agents (available and unavailable) sorted by name with categories and workload.
     *
     * @return Collection<int, User>
     */
    public function forOverview(): Collection
    {
        $counts = $this->getActiveTicketCounts();

        $agents = User::query()
            ->where('role', UserRole::Agent->value)
            ->where('is_active', true)
            ->with(['categories' => function ($q) {
                $q->orderBy('name');
            }])
            ->orderBy('name')
            ->get();

        foreach ($agents as $agent) {
            $agent->active_ticket_count = $counts[$agent->id] ?? 0;
        }

        return $agents;
    }

    /**
     * Snapshots of active, available agents for assignment strategy.
     *
     * @return list<AgentCandidate>
     */
    public function available(): array
    {
        $counts = $this->getActiveTicketCounts();

        $agents = User::query()
            ->where('role', UserRole::Agent->value)
            ->where('is_active', true)
            ->where('is_available', true)
            ->with('categories')
            ->get();

        $candidates = [];
        foreach ($agents as $agent) {
            $candidates[] = new AgentCandidate(
                id: (int) $agent->id,
                categoryIds: $agent->categories->pluck('id')->map(fn ($id) => (int) $id)->all(),
                activeTicketCount: $counts[$agent->id] ?? 0,
                maxOpenTickets: $agent->max_open_tickets !== null ? (int) $agent->max_open_tickets : null,
                lastAssignedAt: $agent->last_assigned_at ? CarbonImmutable::instance($agent->last_assigned_at) : null,
            );
        }

        return $candidates;
    }
}

