<?php

namespace App\Services\Sla;

use App\Enums\TicketPriority;
use App\Models\SlaPolicy;
use RuntimeException;

class SlaPolicyRepository
{
    public function forPriority(TicketPriority $priority): SlaPolicy
    {
        $policy = SlaPolicy::where('priority', $priority->value)->first();

        if ($policy === null) {
            throw new RuntimeException("SLA policy not found for priority {$priority->value}");
        }

        return $policy;
    }

    /**
     * @return array<string, SlaPolicy> keyed by priority value
     */
    public function all(): array
    {
        return SlaPolicy::query()->get()->keyBy(fn (SlaPolicy $policy) => $policy->priority instanceof TicketPriority
            ? $policy->priority->value
            : (string) $policy->priority)->all();
    }
}
