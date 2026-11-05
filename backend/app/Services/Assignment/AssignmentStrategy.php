<?php

namespace App\Services\Assignment;

interface AssignmentStrategy
{
    /**
     * @param list<AgentCandidate> $candidates
     */
    public function pick(int $categoryId, array $candidates): ?int;
}

