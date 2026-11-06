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
     * Number of business-timezone days in the daily series, today included.
     */
    public const DAILY_DAYS = 14;

    /**
     * @return array{
     *     team: array{
     *         open: int,
     *         unassigned: int,
     *         critical: int,
     *         sla_within: int,
     *         sla_breached: int,
     *         sla_approaching: int,
     *         resolved_today: int,
     *         resolved_last_7_days: int
     *     },
     *     by_priority: list<array{priority: string, count: int}>,
     *     daily: list<array{date: string, created: int, resolved: int, sla_met: int, sla_missed: int}>
     * }
     */
    public function team(CarbonImmutable $now): array
    {
        $activeStatuses = array_map(fn ($status) => $status->value, TicketStatus::active());
        $nowString = $now->toDateTimeString();

        $row = DB::table('tickets')
            ->whereIn('status', $activeStatuses)
            ->selectRaw("
                COUNT(*) AS `open`,
                SUM(CASE WHEN assigned_agent_id IS NULL THEN 1 ELSE 0 END) AS unassigned,
                SUM(CASE WHEN priority = 'critical' THEN 1 ELSE 0 END) AS critical,
                SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) AS high,
                SUM(CASE WHEN priority = 'medium' THEN 1 ELSE 0 END) AS medium,
                SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END) AS low,
                SUM(CASE WHEN sla_warning_at > ? THEN 1 ELSE 0 END) AS sla_within,
                SUM(CASE WHEN sla_due_at <= ? THEN 1 ELSE 0 END) AS sla_breached,
                SUM(CASE WHEN sla_warning_at <= ? AND sla_due_at > ? THEN 1 ELSE 0 END) AS sla_approaching
            ", [$nowString, $nowString, $nowString, $nowString])
            ->first();

        // The resolved tiles come from the daily series, so the tiles and the chart always agree.
        $daily = $this->daily($now);
        $resolvedPerDay = array_column($daily, 'resolved');

        return [
            'team' => [
                'open' => (int) ($row->open ?? 0),
                'unassigned' => (int) ($row->unassigned ?? 0),
                'critical' => (int) ($row->critical ?? 0),
                'sla_within' => (int) ($row->sla_within ?? 0),
                'sla_breached' => (int) ($row->sla_breached ?? 0),
                'sla_approaching' => (int) ($row->sla_approaching ?? 0),
                'resolved_today' => (int) end($resolvedPerDay),
                'resolved_last_7_days' => array_sum(array_slice($resolvedPerDay, -7)),
            ],
            'by_priority' => [
                ['priority' => 'critical', 'count' => (int) ($row->critical ?? 0)],
                ['priority' => 'high', 'count' => (int) ($row->high ?? 0)],
                ['priority' => 'medium', 'count' => (int) ($row->medium ?? 0)],
                ['priority' => 'low', 'count' => (int) ($row->low ?? 0)],
            ],
            'daily' => $daily,
        ];
    }

    /**
     * Ticket activity per business-timezone day, oldest first and today last.
     *
     * @return list<array{date: string, created: int, resolved: int, sla_met: int, sla_missed: int}>
     */
    public function daily(CarbonImmutable $now): array
    {
        $timezone = config('tickets.timezone', 'Asia/Dhaka');
        $todayStart = $now->setTimezone($timezone)->startOfDay();

        // Local midnights from the first day of the window to the day after today.
        $dayStarts = [];
        for ($offset = self::DAILY_DAYS - 1; $offset >= -1; $offset--) {
            $dayStarts[] = $todayStart->subDays($offset);
        }

        $bounds = array_map(
            fn (CarbonImmutable $start) => $start->setTimezone('UTC')->toDateTimeString(),
            $dayStarts,
        );
        $windowStart = $bounds[0];
        $windowEnd = $bounds[self::DAILY_DAYS];

        [$createdDayIndex, $createdBindings] = $this->dayIndexSql('created_at', $bounds);

        $created = DB::table('tickets')
            ->where('created_at', '>=', $windowStart)
            ->where('created_at', '<', $windowEnd)
            ->selectRaw("{$createdDayIndex} AS day_index, COUNT(*) AS created", $createdBindings)
            ->groupBy('day_index')
            ->pluck('created', 'day_index');

        // A ticket is finished when resolved, or when closed without a resolution.
        // With a resolution time, the finish day is the resolution day, so one
        // grouping serves both `resolved` and the SLA split (see Ticket::scopeWhereSlaStatus).
        // The two ranges are a UNION ALL so each one uses its own index;
        // MySQL does not merge the resolved_at and closed_at indexes for an OR.
        $closedStatuses = array_map(fn ($status) => $status->value, TicketStatus::closedView());

        $resolvedInWindow = DB::table('tickets')
            ->whereIn('status', $closedStatuses)
            ->where('resolved_at', '>=', $windowStart)
            ->where('resolved_at', '<', $windowEnd)
            ->select(['resolved_at AS finished_at', 'sla_due_at'])
            ->selectRaw('1 AS was_resolved');

        $closedWithoutResolution = DB::table('tickets')
            ->whereIn('status', $closedStatuses)
            ->whereNull('resolved_at')
            ->where('closed_at', '>=', $windowStart)
            ->where('closed_at', '<', $windowEnd)
            ->select(['closed_at AS finished_at', 'sla_due_at'])
            ->selectRaw('0 AS was_resolved');

        [$finishedDayIndex, $finishedBindings] = $this->dayIndexSql('finished_at', $bounds);

        $finished = DB::query()
            ->fromSub($resolvedInWindow->unionAll($closedWithoutResolution), 'finished')
            ->selectRaw("
                {$finishedDayIndex} AS day_index,
                SUM(was_resolved) AS resolved,
                SUM(CASE WHEN finished_at <= sla_due_at THEN 1 ELSE 0 END) AS sla_met,
                SUM(CASE WHEN finished_at > sla_due_at THEN 1 ELSE 0 END) AS sla_missed
            ", $finishedBindings)
            ->groupBy('day_index')
            ->get()
            ->keyBy('day_index');

        $days = [];
        for ($index = 0; $index < self::DAILY_DAYS; $index++) {
            $finishedDay = $finished->get($index);
            $days[] = [
                'date' => $dayStarts[$index]->toDateString(),
                'created' => (int) ($created[$index] ?? 0),
                'resolved' => (int) ($finishedDay->resolved ?? 0),
                'sla_met' => (int) ($finishedDay->sla_met ?? 0),
                'sla_missed' => (int) ($finishedDay->sla_missed ?? 0),
            ];
        }

        return $days;
    }

    /**
     * Builds a CASE expression mapping a timestamp column to its day index in the window.
     *
     * The caller filters the column to the window, so the last day needs no bound.
     *
     * @param  list<string>  $bounds  UTC day starts, one more than the number of days
     * @return array{0: string, 1: list<string>}
     */
    private function dayIndexSql(string $column, array $bounds): array
    {
        $sql = 'CASE';
        $bindings = [];
        for ($index = 0; $index < self::DAILY_DAYS - 1; $index++) {
            $sql .= " WHEN {$column} < ? THEN {$index}";
            $bindings[] = $bounds[$index + 1];
        }
        $sql .= ' ELSE '.(self::DAILY_DAYS - 1).' END';

        return [$sql, $bindings];
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
            ->selectRaw('
                COUNT(*) AS `open`,
                SUM(CASE WHEN sla_due_at <= ? THEN 1 ELSE 0 END) AS sla_breached,
                SUM(CASE WHEN sla_warning_at <= ? AND sla_due_at > ? THEN 1 ELSE 0 END) AS sla_approaching
            ', [$nowString, $nowString, $nowString])
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
     *     sla_within: int,
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
            ->selectRaw('
                assigned_agent_id,
                COUNT(*) AS `open`,
                SUM(CASE WHEN sla_warning_at > ? THEN 1 ELSE 0 END) AS sla_within,
                SUM(CASE WHEN sla_due_at <= ? THEN 1 ELSE 0 END) AS sla_breached,
                SUM(CASE WHEN sla_warning_at <= ? AND sla_due_at > ? THEN 1 ELSE 0 END) AS sla_approaching
            ', [$nowString, $nowString, $nowString, $nowString])
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
                'sla_within' => (int) ($counts->sla_within ?? 0),
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
                SUM(CASE WHEN status IN ('".implode("','", $activeStatuses)."') THEN 1 ELSE 0 END) AS `open`,
                SUM(CASE WHEN status = 'waiting_for_customer' THEN 1 ELSE 0 END) AS waiting_for_customer,
                SUM(CASE WHEN status IN ('".implode("','", $closedStatuses)."') THEN 1 ELSE 0 END) AS `closed`
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
