<?php

namespace App\Services\Sla;

use App\Enums\TicketPriority;
use Carbon\CarbonImmutable;

class SlaCalculator
{
    public function __construct(
        private BusinessScheduleRepository $scheduleRepository,
        private SlaPolicyRepository $policyRepository,
    ) {}

    public function deadlinesFor(TicketPriority $priority, CarbonImmutable $start): SlaDeadlines
    {
        return $this->snapshot()->deadlinesFor($priority, $start);
    }

    /**
     * Loads the policies and the business calendar once, for computing many deadlines without queries.
     */
    public function snapshot(): SlaSnapshot
    {
        return new SlaSnapshot(
            $this->policyRepository->all(),
            new BusinessHoursCalculator($this->scheduleRepository->current()),
        );
    }
}
