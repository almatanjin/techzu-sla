<?php

namespace Database\Seeders;

use App\Enums\TicketActivityType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\User;
use App\Services\Sla\SlaCalculator;
use App\Services\Sla\SlaEscalationService;
use App\Services\Tickets\TicketNumberGenerator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoTicketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction() || Ticket::exists()) {
            return;
        }

        $customers = User::where('role', UserRole::Customer)->get();
        $agents = User::where('role', UserRole::Agent)->get();
        $categories = Category::all();

        if ($customers->isEmpty() || $categories->isEmpty()) {
            return;
        }

        $generator = app(TicketNumberGenerator::class);
        $slaCalculator = app(SlaCalculator::class);

        $priorities = [
            TicketPriority::Low,
            TicketPriority::Medium,
            TicketPriority::High,
            TicketPriority::Critical,
        ];

        // Fixed status pattern for each customer's 8 tickets so every customer has tickets in all statuses
        $customerStatuses = [
            TicketStatus::Open,
            TicketStatus::InProgress,
            TicketStatus::WaitingForCustomer,
            TicketStatus::Resolved,
            TicketStatus::Closed,
            TicketStatus::Open,
            TicketStatus::InProgress,
            TicketStatus::Resolved,
        ];

        $sampleSubjects = [
            'Cannot access dashboard reports',
            'Invoice calculation discrepancy in Q3',
            'API webhook failing with 504 timeout',
            'Password reset email not received',
            'Feature request: CSV export for metrics',
            'Database connection error during sync',
            'Update billing contact address',
            'Mobile layout broken on Safari iOS',
            'SSO login redirect loop',
            'Request for audit logs download',
        ];

        $baseDate = Carbon::now()->subDays(30);
        $ticketIndex = 0;

        $now = CarbonImmutable::now();
        $approachingStart = null;
        for ($step = 1; $step <= 1000; $step++) {
            $time = $now->subMinutes($step * 15);
            $deadlines = $slaCalculator->deadlinesFor(TicketPriority::Low, $time);
            if ($deadlines->warningAt->lte($now) && $deadlines->dueAt->gt($now)) {
                $approachingStart = $time;
                break;
            }
        }

        foreach ($customers as $cIdx => $customer) {
            // Seed 8 tickets per customer across statuses
            for ($k = 0; $k < 8; $k++) {
                $category = $categories[($cIdx + $k) % $categories->count()];
                $priority = $priorities[($cIdx + $k) % count($priorities)];
                $status = $customerStatuses[$k];

                $subject = $sampleSubjects[($cIdx * 8 + $k) % count($sampleSubjects)] . ' #' . ($ticketIndex + 1);

                $createdAt = (clone $baseDate)->addHours($ticketIndex * 18);
                $updatedAt = (clone $createdAt)->addHours(4);

                $assignedAgent = ($status !== TicketStatus::Open && $agents->isNotEmpty())
                    ? $agents[($cIdx + $k) % $agents->count()]
                    : null;

                $resolvedAt = in_array($status, [TicketStatus::Resolved, TicketStatus::Closed], true)
                    ? (clone $createdAt)->addHours(2)
                    : null;

                $closedAt = ($status === TicketStatus::Closed)
                    ? (clone $createdAt)->addHours(5)
                    : null;

                $customDeadlines = null;
                if ($ticketIndex === 39) {
                    $status = TicketStatus::Open;
                    $priority = TicketPriority::Low;
                    $createdAt = Carbon::now()->subMinutes(5);
                    $updatedAt = clone $createdAt;
                    $resolvedAt = null;
                    $closedAt = null;
                } elseif ($ticketIndex === 38 && $approachingStart !== null) {
                    $status = TicketStatus::Open;
                    $priority = TicketPriority::Low;
                    $createdAt = Carbon::instance($approachingStart);
                    $updatedAt = clone $createdAt;
                    $resolvedAt = null;
                    $closedAt = null;
                } elseif ($ticketIndex === 37) {
                    $status = TicketStatus::Resolved;
                    $priority = TicketPriority::Medium;
                    $createdAt = Carbon::now()->subDays(3);
                    $deadlines = $slaCalculator->deadlinesFor($priority, CarbonImmutable::instance($createdAt));
                    $resolvedAt = Carbon::instance($deadlines->warningAt);
                    $updatedAt = clone $resolvedAt;
                    $closedAt = null;
                } elseif ($ticketIndex === 36) {
                    $status = TicketStatus::Resolved;
                    $priority = TicketPriority::Medium;
                    $createdAt = Carbon::now()->subDays(4);
                    $deadlines = $slaCalculator->deadlinesFor($priority, CarbonImmutable::instance($createdAt));
                    $resolvedAt = Carbon::instance($deadlines->dueAt)->addHours(2);
                    $updatedAt = clone $resolvedAt;
                    $closedAt = null;
                } elseif ($ticketIndex === 35) {
                    $status = TicketStatus::InProgress;
                    $priority = TicketPriority::High;
                    $assignedAgent = $agents[0] ?? null;
                    $createdAt = Carbon::now()->subMinutes(10);
                    $updatedAt = clone $createdAt;
                    $resolvedAt = null;
                    $closedAt = null;
                    $customDeadlines = (object) [
                        'policyMinutes' => 120,
                        'startedAt' => CarbonImmutable::now()->subMinutes(10),
                        'warningAt' => CarbonImmutable::now()->addMinutes(3),
                        'dueAt' => CarbonImmutable::now()->addMinutes(12),
                    ];
                }

                DB::transaction(function () use (
                    $generator,
                    $slaCalculator,
                    $customer,
                    $category,
                    $assignedAgent,
                    $priority,
                    $status,
                    $subject,
                    $createdAt,
                    $updatedAt,
                    $resolvedAt,
                    $closedAt,
                    $ticketIndex,
                    $customDeadlines
                ) {
                    $ticketNumber = $generator->next();
                    $deadlines = $customDeadlines ?? $slaCalculator->deadlinesFor($priority, CarbonImmutable::instance($createdAt));

                    $ticket = Ticket::create([
                        'ticket_number' => $ticketNumber,
                        'customer_id' => $customer->id,
                        'category_id' => $category->id,
                        'created_by_id' => $customer->id,
                        'assigned_agent_id' => $assignedAgent?->id,
                        'first_assigned_at' => $assignedAgent ? (clone $createdAt)->addMinute() : null,
                        'priority' => $priority,
                        'status' => $status,
                        'subject' => $subject,
                        'description' => "Detailed issue description for {$subject}. Customer reported this via demo portal.",
                        'resolved_at' => $resolvedAt,
                        'closed_at' => $closedAt,
                        'lock_version' => 1,
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                        'sla_policy_minutes' => $deadlines->policyMinutes,
                        'sla_started_at' => $deadlines->startedAt,
                        'sla_warning_at' => $deadlines->warningAt,
                        'sla_due_at' => $deadlines->dueAt,
                    ]);

                    // 1. Created activity
                    TicketActivity::create([
                        'ticket_id' => $ticket->id,
                        'actor_id' => $customer->id,
                        'type' => TicketActivityType::Created,
                        'old_values' => null,
                        'new_values' => null,
                        'created_at' => $createdAt,
                    ]);

                    // 2. Assigned activity right after created if assigned
                    if ($assignedAgent !== null) {
                        $admin = User::where('role', UserRole::Admin)->first();
                        $assignedActor = ($ticketIndex % 2 === 0) ? null : $admin;

                        TicketActivity::create([
                            'ticket_id' => $ticket->id,
                            'actor_id' => $assignedActor?->id,
                            'type' => TicketActivityType::Assigned,
                            'old_values' => null,
                            'new_values' => [
                                'agent' => [
                                    'id' => (int) $assignedAgent->id,
                                    'name' => (string) $assignedAgent->name,
                                ],
                            ],
                            'created_at' => (clone $createdAt)->addMinute(),
                        ]);
                    }

                    // 3. Status change activity if non-open
                    if ($status !== TicketStatus::Open) {
                        TicketActivity::create([
                            'ticket_id' => $ticket->id,
                            'actor_id' => $assignedAgent?->id,
                            'type' => TicketActivityType::StatusChanged,
                            'old_values' => [
                                'status' => TicketStatus::Open->value,
                            ],
                            'new_values' => [
                                'status' => $status->value,
                            ],
                            'created_at' => $updatedAt,
                        ]);
                    }
                });

                $ticketIndex++;
            }
        }

        foreach ($agents as $agent) {
            $latest = Ticket::where('assigned_agent_id', $agent->id)
                ->whereNotNull('first_assigned_at')
                ->max('first_assigned_at');
            if ($latest) {
                $agent->last_assigned_at = $latest;
                $agent->save();
            }
        }

        app(SlaEscalationService::class)->run(CarbonImmutable::now());
    }
}
