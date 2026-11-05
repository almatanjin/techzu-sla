<?php

namespace App\Search;

use App\Data\TicketFilterData;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TicketQueryBuilder
{
    public function query(User $user, TicketFilterData $filters): Builder
    {
        $filters = $this->trimForRole($user, $filters);
        $term = TicketSearchTerm::parse($filters->search);

        $query = Ticket::query()->visibleTo($user);

        $this->applySearch($query, $term);
        $this->applyStatusOrView($query, $filters, $term);
        $this->applyCategory($query, $filters);
        $this->applyPriority($query, $filters);
        $this->applyAssignment($query, $user, $filters);
        $this->applyCustomer($query, $filters);
        $this->applyDateRange($query, $filters);
        $this->applySlaStatus($query, $filters);
        $this->applySort($query, $filters);

        return $query;
    }

    public function paginate(User $user, TicketFilterData $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->query($user, $filters)
            ->with([
                'category:id,name',
                'customer:id,name,email',
                'assignedAgent:id,name',
            ])
            ->paginate($perPage);
    }

    public function trimForRole(User $user, TicketFilterData $filters): TicketFilterData
    {
        if ($user->isCustomer()) {
            $sort = ($filters->sort === 'sla_due_at' || $filters->sort === '-sla_due_at') ? null : $filters->sort;

            return new TicketFilterData(
                view: $filters->view,
                status: $filters->status,
                priority: $filters->priority,
                categoryId: $filters->categoryId,
                search: $filters->search,
                assigned: null,
                customerId: null,
                createdFrom: $filters->createdFrom,
                createdTo: $filters->createdTo,
                slaStatus: null,
                sort: $sort,
                perPage: $filters->perPage,
            );
        }

        if ($user->isAgent()) {
            $assigned = $filters->assigned;
            if ($assigned !== null && $assigned !== 'me' && $assigned !== 'unassigned') {
                $assigned = null;
            }

            return new TicketFilterData(
                view: $filters->view,
                status: $filters->status,
                priority: $filters->priority,
                categoryId: $filters->categoryId,
                search: $filters->search,
                assigned: $assigned,
                customerId: $filters->customerId,
                createdFrom: $filters->createdFrom,
                createdTo: $filters->createdTo,
                slaStatus: $filters->slaStatus,
                sort: $filters->sort,
                perPage: $filters->perPage,
            );
        }

        return $filters;
    }

    protected function applySearch(Builder $query, TicketSearchTerm $term): void
    {
        if ($term->isNumber() && $term->number !== null) {
            $query->where('ticket_number', $term->number);
        } elseif ($term->isKeywords() && $term->expression !== null) {
            $query->whereFullText(['subject', 'description'], $term->expression, ['mode' => 'boolean']);
        }
    }

    protected function applyStatusOrView(Builder $query, TicketFilterData $filters, TicketSearchTerm $term): void
    {
        if ($filters->status !== null) {
            $query->where('status', $filters->status->value);
        } elseif ($term->isNumber()) {
            return;
        } elseif ($filters->view === 'closed') {
            $statuses = TicketStatus::closedView();
            $query->whereIn('status', array_map(fn ($s) => $s->value, $statuses));
        } elseif ($filters->view !== 'all') {
            $statuses = TicketStatus::active();
            $query->whereIn('status', array_map(fn ($s) => $s->value, $statuses));
        }
    }

    protected function applyCategory(Builder $query, TicketFilterData $filters): void
    {
        if ($filters->categoryId !== null) {
            $query->where('category_id', $filters->categoryId);
        }
    }

    protected function applyPriority(Builder $query, TicketFilterData $filters): void
    {
        if ($filters->priority !== null) {
            $query->where('priority', $filters->priority->value);
        }
    }

    protected function applyAssignment(Builder $query, User $user, TicketFilterData $filters): void
    {
        if ($filters->assigned === 'me') {
            $query->where('assigned_agent_id', $user->id);
        } elseif ($filters->assigned === 'unassigned') {
            $query->whereNull('assigned_agent_id');
        } elseif ($filters->assigned !== null) {
            $query->where('assigned_agent_id', (int) $filters->assigned);
        }
    }

    protected function applyCustomer(Builder $query, TicketFilterData $filters): void
    {
        if ($filters->customerId !== null) {
            $query->where('customer_id', $filters->customerId);
        }
    }

    protected function applyDateRange(Builder $query, TicketFilterData $filters): void
    {
        $tz = config('tickets.timezone', 'Asia/Dhaka');

        if ($filters->createdFrom !== null && $filters->createdFrom !== '') {
            $from = CarbonImmutable::parse($filters->createdFrom, $tz)->startOfDay()->utc();
            $query->where('created_at', '>=', $from);
        }

        if ($filters->createdTo !== null && $filters->createdTo !== '') {
            $to = CarbonImmutable::parse($filters->createdTo, $tz)->addDay()->startOfDay()->utc();
            $query->where('created_at', '<', $to);
        }
    }

    protected function applySlaStatus(Builder $query, TicketFilterData $filters): void
    {
        if ($filters->slaStatus !== null) {
            $query->whereSlaStatus($filters->slaStatus);
        }
    }

    protected function applySort(Builder $query, TicketFilterData $filters): void
    {
        $sort = $filters->sort ?? '-created_at';

        match ($sort) {
            'created_at' => $query->orderBy('created_at', 'asc')->orderBy('id', 'asc'),
            '-created_at' => $query->orderBy('created_at', 'desc')->orderBy('id', 'desc'),
            'updated_at' => $query->orderBy('updated_at', 'asc')->orderBy('id', 'asc'),
            '-updated_at' => $query->orderBy('updated_at', 'desc')->orderBy('id', 'desc'),
            'priority' => $query->orderByRaw("FIELD(priority, 'low', 'medium', 'high', 'critical') ASC")->orderByDesc('created_at')->orderByDesc('id'),
            '-priority' => $query->orderByRaw("FIELD(priority, 'low', 'medium', 'high', 'critical') DESC")->orderByDesc('created_at')->orderByDesc('id'),
            'sla_due_at' => $query->orderBy('sla_due_at', 'asc')->orderBy('id', 'asc'),
            '-sla_due_at' => $query->orderBy('sla_due_at', 'desc')->orderBy('id', 'desc'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }
}
