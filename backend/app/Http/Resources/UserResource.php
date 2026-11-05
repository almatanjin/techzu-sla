<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleValue = $this->role instanceof \BackedEnum ? $this->role->value : $this->role;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $roleValue,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'agent_settings' => $this->isAgent() ? [
                'is_available' => (bool) $this->is_available,
                'max_open_tickets' => $this->max_open_tickets !== null ? (int) $this->max_open_tickets : null,
                'category_ids' => $this->categories->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ] : null,
        ];
    }
}
