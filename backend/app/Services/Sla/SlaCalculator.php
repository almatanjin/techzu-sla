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
        $policy = $this->policyRepository->forPriority($priority);
        $schedule = $this->scheduleRepository->current();
        $calculator = new BusinessHoursCalculator($schedule);

        $policyMinutes = (int) $policy->resolution_minutes;
        $warningMinutes = intdiv($policyMinutes * (int) $policy->warning_threshold_percent, 100);

        $warningAt = $calculator->addBusinessMinutes($start, $warningMinutes);
        $dueAt = $calculator->addBusinessMinutes($start, $policyMinutes);

        return new SlaDeadlines(
            policyMinutes: $policyMinutes,
            startedAt: $start->setTimezone('UTC'),
            warningAt: $warningAt,
            dueAt: $dueAt,
        );
    }
}

