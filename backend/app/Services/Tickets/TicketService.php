<?php

namespace App\Services\Tickets;

use App\Data\ChangeStatusData;
use App\Data\CreateTicketData;
use App\Data\TicketFilterData;
use App\Data\UpdateTicketData;
use App\Enums\TicketActivityType;
use App\Enums\TicketStatus;
use App\Exceptions\TicketVersionConflictException;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Sla\SlaCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function __construct(
        private readonly TicketNumberGenerator $numberGenerator,
        private readonly TicketActivityLogger $activityLogger,
        private readonly TicketWorkflow $workflow,
        private readonly SlaCalculator $slaCalculator,
    ) {}

    public function list(User $user, TicketFilterData|string $filter = 'active', int $perPage = 20): LengthAwarePaginator
    {
        if (is_string($filter)) {
            $filter = new TicketFilterData(view: $filter);
        }

        $query = Ticket::query()->visibleTo($user);

        if ($filter->status !== null) {
            $query->where('status', $filter->status->value);
        } elseif ($filter->view === 'closed') {
            $statuses = TicketStatus::closedView();
            $query->whereIn('status', array_map(fn ($s) => $s->value, $statuses));
        } elseif ($filter->view !== 'all') {
            $statuses = TicketStatus::active();
            $query->whereIn('status', array_map(fn ($s) => $s->value, $statuses));
        }

        if ($filter->categoryId !== null) {
            $query->where('category_id', $filter->categoryId);
        }

        if ($filter->priority !== null) {
            $query->where('priority', $filter->priority->value);
        }

        if ($filter->search !== null && $filter->search !== '') {
            $term = '%' . $filter->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('ticket_number', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        if ($filter->assigned === 'me') {
            $query->where('assigned_agent_id', $user->id);
        } elseif ($filter->assigned === 'unassigned') {
            $query->whereNull('assigned_agent_id');
        }

        if ($filter->slaStatus !== null) {
            $query->whereSlaStatus($filter->slaStatus);
        }

        return $query
            ->with([
                'category:id,name',
                'customer:id,name,email',
                'assignedAgent:id,name',
            ])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function create(CreateTicketData $data, User $actor): Ticket
    {
        return DB::transaction(function () use ($data, $actor): Ticket {
            $ticketNumber = $this->numberGenerator->next();
            $deadlines = $this->slaCalculator->deadlinesFor($data->priority, CarbonImmutable::now());

            $ticket = new Ticket([
                'ticket_number' => $ticketNumber,
                'subject' => $data->subject,
                'description' => $data->description,
                'priority' => $data->priority,
                'status' => TicketStatus::Open,
                'category_id' => $data->categoryId,
                'customer_id' => $data->customerId,
                'created_by_id' => $actor->id,
                'assigned_agent_id' => null,
                'lock_version' => 0,
                'sla_policy_minutes' => $deadlines->policyMinutes,
                'sla_started_at' => $deadlines->startedAt,
                'sla_warning_at' => $deadlines->warningAt,
                'sla_due_at' => $deadlines->dueAt,
            ]);
            $ticket->save();

            $this->activityLogger->log(
                $ticket,
                TicketActivityType::Created,
                $actor
            );

            $ticket->load(['category', 'customer', 'createdBy', 'assignedAgent']);

            return $ticket;
        });
    }

    public function update(Ticket $ticket, UpdateTicketData $data, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $data, $actor): Ticket {
            /** @var Ticket $fresh */
            $fresh = Ticket::query()->where('id', $ticket->id)->lockForUpdate()->firstOrFail();

            if ($fresh->lock_version !== $data->lockVersion) {
                throw new TicketVersionConflictException($fresh);
            }

            $fresh->loadMissing(['category']);

            $activitiesToLog = [];

            if ($data->hasSubject && $data->subject !== null && $data->subject !== $fresh->subject) {
                $activitiesToLog[] = [
                    'type' => TicketActivityType::SubjectChanged,
                    'old' => ['subject' => $fresh->subject],
                    'new' => ['subject' => $data->subject],
                ];
                $fresh->subject = $data->subject;
            }

            if ($data->hasDescription && $data->description !== null && $data->description !== $fresh->description) {
                $activitiesToLog[] = [
                    'type' => TicketActivityType::DescriptionChanged,
                    'old' => ['description' => $fresh->description],
                    'new' => ['description' => $data->description],
                ];
                $fresh->description = $data->description;
            }

            if ($data->hasPriority && $data->priority !== null && $data->priority !== $fresh->priority) {
                $deadlines = $this->slaCalculator->deadlinesFor($data->priority, CarbonImmutable::instance($fresh->sla_started_at));
                $fresh->sla_policy_minutes = $deadlines->policyMinutes;
                $fresh->sla_warning_at = $deadlines->warningAt;
                $fresh->sla_due_at = $deadlines->dueAt;

                $activitiesToLog[] = [
                    'type' => TicketActivityType::PriorityChanged,
                    'old' => ['priority' => $fresh->priority->value],
                    'new' => ['priority' => $data->priority->value],
                ];
                $fresh->priority = $data->priority;
            }

            if ($data->hasCategoryId && $data->categoryId !== null && $data->categoryId !== $fresh->category_id) {
                $newCategory = Category::findOrFail($data->categoryId);
                $activitiesToLog[] = [
                    'type' => TicketActivityType::CategoryChanged,
                    'old' => ['category' => ['id' => $fresh->category->id, 'name' => $fresh->category->name]],
                    'new' => ['category' => ['id' => $newCategory->id, 'name' => $newCategory->name]],
                ];
                $fresh->category_id = $data->categoryId;
            }

            if (! empty($activitiesToLog)) {
                $fresh->lock_version += 1;
                $fresh->save();

                foreach ($activitiesToLog as $entry) {
                    $this->activityLogger->log(
                        $fresh,
                        $entry['type'],
                        $actor,
                        $entry['old'],
                        $entry['new']
                    );
                }
            }

            $fresh->load(['category', 'customer', 'createdBy', 'assignedAgent']);

            return $fresh;
        });
    }

    public function changeStatus(Ticket $ticket, ChangeStatusData $data, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $data, $actor): Ticket {
            /** @var Ticket $fresh */
            $fresh = Ticket::query()->where('id', $ticket->id)->lockForUpdate()->firstOrFail();

            if ($fresh->lock_version !== $data->lockVersion) {
                throw new TicketVersionConflictException($fresh);
            }

            $this->workflow->assertCanTransition($fresh, $actor, $data->status);

            $oldStatus = $fresh->status;
            $newStatus = $data->status;

            $fresh->status = $newStatus;
            $fresh->lock_version += 1;

            if ($newStatus === TicketStatus::Resolved) {
                $fresh->resolved_at = now();
            } elseif ($newStatus === TicketStatus::Closed) {
                $fresh->closed_at = now();
            }

            // Reopening from resolved clears resolved_at
            if ($oldStatus === TicketStatus::Resolved && in_array($newStatus, [TicketStatus::Open, TicketStatus::InProgress], true)) {
                $fresh->resolved_at = null;
            }

            $fresh->save();

            $this->activityLogger->log(
                $fresh,
                TicketActivityType::StatusChanged,
                $actor,
                ['status' => $oldStatus->value],
                ['status' => $newStatus->value]
            );

            $fresh->load(['category', 'customer', 'createdBy', 'assignedAgent']);

            return $fresh;
        });
    }
}
