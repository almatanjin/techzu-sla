<?php

namespace App\Data;

readonly class UpdateSlaPolicyData
{
    public function __construct(
        public ?int $resolutionMinutes = null,
        public ?int $warningThresholdPercent = null,
    ) {}
}

