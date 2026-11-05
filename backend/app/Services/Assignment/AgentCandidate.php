<?php

namespace App\Services\Assignment;

use Carbon\CarbonImmutable;

readonly class AgentCandidate
{
    /**
     * @param list<int> $categoryIds
     */
    public function __construct(
        public int $id,
        public array $categoryIds,
        public int $activeTicketCount,
        public ?int $maxOpenTickets = null,
        public ?CarbonImmutable $lastAssignedAt = null,
    ) {}
}

