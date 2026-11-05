<?php

namespace App\Services\Sla;

use Carbon\CarbonImmutable;

final readonly class SlaDeadlines
{
    public function __construct(
        public int $policyMinutes,
        public CarbonImmutable $startedAt,
        public CarbonImmutable $warningAt,
        public CarbonImmutable $dueAt,
    ) {}
}

