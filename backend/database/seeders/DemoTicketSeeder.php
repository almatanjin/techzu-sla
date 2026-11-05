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
use App\Services\Tickets\TicketNumberGenerator;
use Carbon\Carbon;
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

        $priorities = [
            TicketPriority::Low,
            TicketPriority::Medium,
            TicketPriority::High,
            TicketPriority::Critical,
        ];

        $statuses = [
            TicketStatus::Open,
            TicketStatus::InProgress,
            TicketStatus::WaitingForCustomer,
            TicketStatus::Resolved,
            TicketStatus::Closed,
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

        for ($i = 0; $i < 40; $i++) {
            $customer = $customers[$i % $customers->count()];
            $category = $categories[$i % $categories->count()];
            $priority = $priorities[$i % count($priorities)];
            $status = $statuses[$i % count($statuses)];
            $subject = $sampleSubjects[$i % count($sampleSubjects)] . ' #' . ($i + 1);

            $createdAt = (clone $baseDate)->addHours($i * 18);
            $updatedAt = (clone $createdAt)->addHours(4);

            $assignedAgent = ($status !== TicketStatus::Open && $agents->isNotEmpty())
                ? $agents[$i % $agents->count()]
                : null;

            $resolvedAt = in_array($status, [TicketStatus::Resolved, TicketStatus::Closed], true)
                ? (clone $createdAt)->addHours(2)
                : null;

            $closedAt = ($status === TicketStatus::Closed)
                ? (clone $createdAt)->addHours(5)
                : null;

            DB::transaction(function () use (
                $generator,
                $customer,
                $category,
                $assignedAgent,
                $priority,
                $status,
                $subject,
                $createdAt,
                $updatedAt,
                $resolvedAt,
                $closedAt
            ) {
                $ticketNumber = $generator->next();

                $ticket = Ticket::create([
                    'ticket_number' => $ticketNumber,
                    'customer_id' => $customer->id,
                    'category_id' => $category->id,
                    'created_by_id' => $customer->id,
                    'assigned_agent_id' => $assignedAgent?->id,
                    'priority' => $priority,
                    'status' => $status,
                    'subject' => $subject,
                    'description' => "Detailed issue description for {$subject}. Customer reported this via demo portal.",
                    'resolved_at' => $resolvedAt,
                    'closed_at' => $closedAt,
                    'lock_version' => 1,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
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

                // 2. Status change activity if non-open
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
        }
    }
}
