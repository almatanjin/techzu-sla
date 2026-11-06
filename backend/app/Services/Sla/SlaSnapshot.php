<?php

namespace App\Services\Sla;

use App\Enums\TicketPriority;
use App\Models\SlaPolicy;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * SLA policies and the business calendar loaded once, for computing many deadlines without queries.
 *
 * Take a snapshot for one unit of work (for example one import chunk); a policy or calendar
 * edit applies to snapshots taken after it.
 */
class SlaSnapshot
{
    /**
     * @param  array<string, SlaPolicy>  $policiesByPriority
     */
    public function __construct(
        private readonly array $policiesByPriority,
        private readonly BusinessHoursCalculator $calculator,
    ) {}

    public function deadlinesFor(TicketPriority $priority, CarbonImmutable $start): SlaDeadlines
    {
        $policy = $this->policiesByPriority[$priority->value]
            ?? throw new RuntimeException("SLA policy not found for priority {$priority->value}");

        $policyMinutes = (int) $policy->resolution_minutes;
        $warningMinutes = intdiv($policyMinutes * (int) $policy->warning_threshold_percent, 100);

        return new SlaDeadlines(
            policyMinutes: $policyMinutes,
            startedAt: $start->setTimezone('UTC'),
            warningAt: $this->calculator->addBusinessMinutes($start, $warningMinutes),
            dueAt: $this->calculator->addBusinessMinutes($start, $policyMinutes),
        );
    }
}
