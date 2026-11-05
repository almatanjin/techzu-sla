<?php

namespace App\Http\Resources;

use App\Models\SlaPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SlaPolicy
 */
class SlaPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'priority' => $this->priority instanceof \BackedEnum ? $this->priority->value : $this->priority,
            'resolution_minutes' => (int) $this->resolution_minutes,
            'warning_threshold_percent' => (int) $this->warning_threshold_percent,
        ];
    }
}

