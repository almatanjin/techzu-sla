<?php

namespace App\Http\Resources;

use App\Models\TicketImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketImport
 */
class TicketImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_filename' => $this->original_filename,
            'status' => $this->status->value,
            'total_rows' => (int) $this->total_rows,
            'processed_rows' => (int) $this->processed_rows,
            'imported_rows' => (int) $this->imported_rows,
            'failed_rows' => (int) $this->failed_rows,
            'duplicate_rows' => (int) $this->duplicate_rows,
            'failure_message' => $this->failure_message,
            'user' => [
                'id' => $this->user_id,
                'name' => $this->user?->name,
            ],
            'user_name' => $this->user?->name,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

