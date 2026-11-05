<?php

namespace App\Dashboard;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @return array{
     *     team: array{
     *         open: int,
     *         unassigned: int,
     *         critical: int,
     *         sla_breached: int,
     *         sla_approaching: int,
     *         resolved_today: int,
     *         resolved_last_7_days: int
     *     },
     *     by_priority: list<array{priority: string, count: int}>
     * }
     */
    public function team(CarbonImmutable $now): array
    {
        $activeStatuses = array_map(fn ($status) => $status->value, TicketStatus::active());
        $nowString = $now->toDateTimeString();

        $timezone = config('tickets.timezone', 'Asia/Dhaka');
        $nowInTz = $now->setTimezone($timezone);
        $todayStartUtc = $nowInTz->startOfDay()->setTimezone('UTC')->toDateTimeString();
        $sevenDaysAgoUtc = $nowInTz->subDays(6)->startOfDay()->setTimezone('UTC')->toDateTimeString();

        $row = DB::table('tickets')
            ->whereIn('status', $activeStatuses)
            ->selectRaw("
                COUNT(*) AS `open`,
                SUM(CASE WHEN assigned_agent_id IS NULL THEN 1 ELSE 0 END) AS unassigned,
                SUM(CASE WHEN priority = 'critical' THEN 1 ELSE 0 END) AS critical,
                SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) AS high,
                SUM(CASE WHEN priority = 'medium' THEN 1 ELSE 0 END) AS medium,
                SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END) AS low,
                SUM(CASE WHEN sla_due_at <= ? THEN 1 ELSE 0 END) AS sla_breached,
                SUM(CASE WHEN sla_warning_at <= ? AND sla_due_at > ? THEN 1 ELSE 0 END) AS sla_approaching
            ", [$nowString, $nowString, $nowString])
            ->first();

        $resolvedRow = DB::table('tickets')
            ->where('resolved_at', '>=', $sevenDaysAgoUtc)
            ->selectRaw('
                SUM(CASE WHEN resolved_at >= ? THEN 1 ELSE 0 END) AS resolved_today,
                COUNT(*) AS resolved_last_7_days
            ', [$todayStartUtc])
            ->first();

        return [
            'team' => [
                'open' => (int) ($row->open ?? 0),
                'unassigned' => (int) ($row->unassigned ?? 0),
                'critical' => (int) ($row->critical ?? 0),
                'sla_breached' => (int) ($row->sla_breached ?? 0),
                'sla_approaching' => (int) ($row->sla_approaching ?? 0),
                'resolved_today' => (int) ($resolvedRow->resolved_today ?? 0),
                'resolved_last_7_days' => (int) ($resolvedRow->resolved_last_7_days ?? 0),
            ],
            'by_priority' => [
                ['priority' => 'critical', 'count' => (int) ($row->critical ?? 0)],
                ['priority' => 'high', 'count' => (int) ($row->high ?? 0)],
                ['priority' => 'medium', 'count' => (int) ($row->medium ?? 0)],
                ['priority' => 'low', 'count' => (int) ($row->low ?? 0)],
            ],
        ];
    }

    /**
     * @return array{open: int, sla_approaching: int, sla_breached: int}
     */
    public function myQueue(User $user, CarbonImmutable $now): array
    {
        $activeStatuses = array_map(fn ($status) => $status->value, TicketStatus::active());
        $nowString = $now->toDateTimeString();

        $row = DB::table('tickets')
            ->where('assigned_agent_id', $user->id)
            ->whereIn('status', $activeStatuses)
            ->selectRaw("
                COUNT(*) AS `open`,
                SUM(CASE WHEN sla_due_at <= ? THEN 1 ELSE 0 END) AS sla_breached,
                SUM(CASE WHEN sla_warning_at <= ? AND sla_due_at > ? THEN 1 ELSE 0 END) AS sla_approaching
            ", [$nowString, $nowString, $nowString])
            ->first();

        return [
            'open' => (int) ($row->open ?? 0),
            'sla_approaching' => (int) ($row->sla_approaching ?? 0),
            'sla_breached' => (int) ($row->sla_breached ?? 0),
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     is_active: bool,
     *     open: int,
     *     sla_approaching: int,
     *     sla_breached: int
     * }>
     */
    public function byAgent(CarbonImmutable $now): array
    {
        $activeStatuses = array_map(fn ($status) => $status->value, TicketStatus::active());
        $nowString = $now->toDateTimeString();

        $ticketCounts = DB::table('tickets')
            ->whereNotNull('assigned_agent_id')
            ->whereIn('status', $activeStatuses)
            ->groupBy('assigned_agent_id')
            ->selectRaw("
                assigned_agent_id,
                COUNT(*) AS `open`,
                SUM(CASE WHEN sla_due_at <= ? THEN 1 ELSE 0 END) AS sla_breached,
                SUM(CASE WHEN sla_warning_at <= ? AND sla_due_at > ? THEN 1 ELSE 0 END) AS sla_approaching
            ", [$nowString, $nowString, $nowString])
            ->get()
            ->keyBy('assigned_agent_id');

        $holderIds = $ticketCounts->keys()->all();

        $users = User::query()
            ->where(function ($query) use ($holderIds) {
                $query->where('role', UserRole::Agent)
                    ->where('is_active', true);
                if (! empty($holderIds)) {
                    $query->orWhereIn('id', $holderIds);
                }
            })
            ->select(['id', 'name', 'is_active'])
            ->orderBy('name')
            ->get();

        return $users->map(function ($user) use ($ticketCounts) {
            $counts = $ticketCounts->get($user->id);

            return [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'is_active' => (bool) $user->is_active,
                'open' => (int) ($counts->open ?? 0),
                'sla_approaching' => (int) ($counts->sla_approaching ?? 0),
                'sla_breached' => (int) ($counts->sla_breached ?? 0),
            ];
        })->values()->all();
    }

    /**
     * @return array{
     *     summary: array{open: int, waiting_for_customer: int, closed: int},
     *     recent_tickets: list<array{ticket_number: string, subject: string, status: string, updated_at: string}>
     * }
     */
    public function customerSummary(User $customer): array
    {
        $activeStatuses = array_map(fn ($status) => $status->value, TicketStatus::active());
        $closedStatuses = array_map(fn ($status) => $status->value, TicketStatus::closedView());

        $row = DB::table('tickets')
            ->where('customer_id', $customer->id)
            ->selectRaw("
                SUM(CASE WHEN status IN ('" . implode("','", $activeStatuses) . "') THEN 1 ELSE 0 END) AS `open`,
                SUM(CASE WHEN status = 'waiting_for_customer' THEN 1 ELSE 0 END) AS waiting_for_customer,
                SUM(CASE WHEN status IN ('" . implode("','", $closedStatuses) . "') THEN 1 ELSE 0 END) AS `closed`
            ")
            ->first();

        $recent = DB::table('tickets')
            ->where('customer_id', $customer->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(5)
            ->select(['ticket_number', 'subject', 'status', 'updated_at'])
            ->get();

        return [
            'summary' => [
                'open' => (int) ($row->open ?? 0),
                'waiting_for_customer' => (int) ($row->waiting_for_customer ?? 0),
                'closed' => (int) ($row->closed ?? 0),
            ],
            'recent_tickets' => $recent->map(fn ($t) => [
                'ticket_number' => (string) $t->ticket_number,
                'subject' => (string) $t->subject,
                'status' => (string) $t->status,
                'updated_at' => CarbonImmutable::parse($t->updated_at)->toIso8601String(),
            ])->values()->all(),
        ];
    }
}



