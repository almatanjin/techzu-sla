<?php

namespace App\Http\Controllers;

use App\Dashboard\CachedDashboard;
use App\Http\Resources\CustomerDashboardResource;
use App\Http\Resources\StaffDashboardResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly CachedDashboard $cachedDashboard,
    ) {}

    public function __invoke(Request $request)
    {
        $user = $request->user();
        if ($user === null || ! $user->is_active) {
            abort(403);
        }

        $now = CarbonImmutable::now();

        if ($user->isCustomer()) {
            $customerBlock = $this->cachedDashboard->customerSummary($user, $now);

            return new CustomerDashboardResource([
                'generated_at' => $customerBlock['computed_at'],
                'summary' => $customerBlock['summary'],
                'recent_tickets' => $customerBlock['recent_tickets'],
            ]);
        }

        $teamBlock = $this->cachedDashboard->team($now);

        $computedAts = [$teamBlock['computed_at']];

        $payload = [
            'team' => $teamBlock['team'],
            'by_priority' => $teamBlock['by_priority'],
            'daily' => $teamBlock['daily'],
        ];

        // Admins see every holder's queue, their own included, in by_agent.
        if (! $user->isAdmin()) {
            $myQueueBlock = $this->cachedDashboard->myQueue($user, $now);
            $computedAts[] = $myQueueBlock['computed_at'];
            $payload['my_queue'] = $myQueueBlock['my_queue'];
        }

        if ($user->isAdmin()) {
            $byAgentBlock = $this->cachedDashboard->byAgent($now);
            $computedAts[] = $byAgentBlock['computed_at'];
            $payload['by_agent'] = $byAgentBlock['by_agent'];
        }

        $payload['generated_at'] = $this->cachedDashboard->oldestGeneratedAt($computedAts);

        return new StaffDashboardResource($payload);
    }
}
