<?php

namespace App\Services\Sla;

class SlaCheckResult
{
    public function __construct(
        public readonly int $warnedCount = 0,
        public readonly int $escalatedCount = 0,
    ) {}
}

