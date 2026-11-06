<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffDashboardResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = [
            'generated_at' => $this->resource['generated_at'],
            'team' => $this->resource['team'],
        ];

        if (array_key_exists('my_queue', $this->resource)) {
            $payload['my_queue'] = $this->resource['my_queue'];
        }

        if (array_key_exists('by_priority', $this->resource)) {
            $payload['by_priority'] = $this->resource['by_priority'];
        }

        if (array_key_exists('daily', $this->resource)) {
            $payload['daily'] = $this->resource['daily'];
        }

        if (array_key_exists('by_agent', $this->resource)) {
            $payload['by_agent'] = $this->resource['by_agent'];
        }

        return $payload;
    }
}
