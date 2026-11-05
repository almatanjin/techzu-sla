<?php

namespace App\Http\Resources;

use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketMessage
 */
class TicketMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'body' => $this->body,
            'author' => $this->author ? [
                'id' => $this->author->id,
                'name' => $this->author->name,
                'role' => $this->author->role instanceof \BackedEnum ? $this->author->role->value : $this->author->role,
            ] : null,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments', fn () => $this->attachments, [])),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

