<?php

namespace App\Services\Assignment;

class CategoryWorkloadStrategy implements AssignmentStrategy
{
    /**
     * @param list<AgentCandidate> $candidates
     */
    public function pick(int $categoryId, array $candidates): ?int
    {
        if (empty($candidates)) {
            return null;
        }

        $matching = array_values(array_filter(
            $candidates,
            fn (AgentCandidate $c) => in_array($categoryId, $c->categoryIds, true)
        ));

        $pool = !empty($matching) ? $matching : $candidates;

        $available = array_values(array_filter(
            $pool,
            fn (AgentCandidate $c) => $c->maxOpenTickets === null || $c->activeTicketCount < $c->maxOpenTickets
        ));

        if (empty($available)) {
            return null;
        }

        usort($available, function (AgentCandidate $a, AgentCandidate $b) {
            if ($a->activeTicketCount !== $b->activeTicketCount) {
                return $a->activeTicketCount <=> $b->activeTicketCount;
            }

            if ($a->lastAssignedAt === null && $b->lastAssignedAt !== null) {
                return -1;
            }
            if ($a->lastAssignedAt !== null && $b->lastAssignedAt === null) {
                return 1;
            }
            if ($a->lastAssignedAt !== null && $b->lastAssignedAt !== null) {
                $timeDiff = $a->lastAssignedAt->getTimestamp() <=> $b->lastAssignedAt->getTimestamp();
                if ($timeDiff !== 0) {
                    return $timeDiff;
                }
            }

            return $a->id <=> $b->id;
        });

        return $available[0]->id;
    }
}
