<?php

namespace App\Dashboard;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class CachedDashboard
{
    public const TTL_SECONDS = 60;

    public function __construct(
        private readonly DashboardService $service,
    ) {}

    /**
     * @return array{
     *     computed_at: string,
     *     team: array<string, int>,
     *     by_priority: list<array{priority: string, count: int}>,
     *     daily: list<array<string, int|string>>
     * }
     */
    public function team(CarbonImmutable $now): array
    {
        return Cache::remember('dashboard:team:v2', self::TTL_SECONDS, function () use ($now) {
            $data = $this->service->team($now);

            return [
                'computed_at' => $now->toIso8601String(),
                'team' => $data['team'],
                'by_priority' => $data['by_priority'],
                'daily' => $data['daily'],
            ];
        });
    }

    /**
     * @return array{
     *     computed_at: string,
     *     my_queue: array{open: int, sla_approaching: int, sla_breached: int}
     * }
     */
    public function myQueue(User $user, CarbonImmutable $now): array
    {
        $key = "dashboard:my-queue:{$user->id}";

        return Cache::remember($key, self::TTL_SECONDS, function () use ($user, $now) {
            return [
                'computed_at' => $now->toIso8601String(),
                'my_queue' => $this->service->myQueue($user, $now),
            ];
        });
    }

    /**
     * @return array{
     *     computed_at: string,
     *     by_agent: list<array{id: int, name: string, is_active: bool, open: int, sla_within: int, sla_approaching: int, sla_breached: int}>
     * }
     */
    public function byAgent(CarbonImmutable $now): array
    {
        return Cache::remember('dashboard:by-agent:v2', self::TTL_SECONDS, function () use ($now) {
            return [
                'computed_at' => $now->toIso8601String(),
                'by_agent' => $this->service->byAgent($now),
            ];
        });
    }

    /**
     * @return array{
     *     computed_at: string,
     *     summary: array{open: int, waiting_for_customer: int, closed: int},
     *     recent_tickets: list<array{ticket_number: string, subject: string, status: string, updated_at: string}>
     * }
     */
    public function customerSummary(User $customer, CarbonImmutable $now): array
    {
        $key = "dashboard:customer:{$customer->id}";

        return Cache::remember($key, self::TTL_SECONDS, function () use ($customer, $now) {
            $data = $this->service->customerSummary($customer);

            return [
                'computed_at' => $now->toIso8601String(),
                'summary' => $data['summary'],
                'recent_tickets' => $data['recent_tickets'],
            ];
        });
    }

    /**
     * @param  list<string>  $computedAts
     */
    public function oldestGeneratedAt(array $computedAts): string
    {
        $oldest = collect($computedAts)
            ->map(fn (string $iso) => CarbonImmutable::parse($iso))
            ->min();

        return $oldest ? $oldest->toIso8601String() : CarbonImmutable::now()->toIso8601String();
    }
}
