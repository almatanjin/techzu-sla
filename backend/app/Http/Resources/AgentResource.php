<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AgentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'is_available' => (bool) $this->is_available,
            'max_open_tickets' => $this->max_open_tickets !== null ? (int) $this->max_open_tickets : null,
            'categories' => $this->categories->map(fn ($category) => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
            ])->values()->all(),
            'active_ticket_count' => (int) ($this->active_ticket_count ?? 0),
        ];
    }
}

