<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerDashboardResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'generated_at' => $this->resource['generated_at'],
            'summary' => $this->resource['summary'],
            'recent_tickets' => $this->resource['recent_tickets'],
        ];
    }
}

