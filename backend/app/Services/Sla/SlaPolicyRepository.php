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
}

