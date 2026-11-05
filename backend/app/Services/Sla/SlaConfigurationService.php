<?php

namespace App\Services\Sla;

use App\Data\UpdateSlaPolicyData;
use App\Enums\TicketPriority;
use App\Models\BusinessHour;
use App\Models\SlaPolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SlaConfigurationService
{
    /**
     * @return Collection<int, SlaPolicy>
     */
    public function listPolicies(): Collection
    {
        return SlaPolicy::all();
    }

    public function updatePolicy(TicketPriority $priority, UpdateSlaPolicyData $data): SlaPolicy
    {
        $policy = SlaPolicy::where('priority', $priority->value)->firstOrFail();

        if ($data->resolutionMinutes !== null) {
            $policy->resolution_minutes = $data->resolutionMinutes;
        }

        if ($data->warningThresholdPercent !== null) {
            $policy->warning_threshold_percent = $data->warningThresholdPercent;
        }

        $policy->save();

        return $policy;
    }

    /**
     * @return array{timezone: string, days: list<array<string, mixed>>}
     */
    public function getBusinessHours(): array
    {
        $hours = BusinessHour::orderBy('day_of_week')->get();

        return [
            'timezone' => (string) config('tickets.timezone', 'Asia/Dhaka'),
            'days' => $hours->map(fn (BusinessHour $h) => [
                'id' => $h->id,
                'day_of_week' => (int) $h->day_of_week,
                'is_working_day' => (bool) $h->is_working_day,
                'opens_at' => $h->opens_at ? substr((string) $h->opens_at, 0, 5) : null,
                'closes_at' => $h->closes_at ? substr((string) $h->closes_at, 0, 5) : null,
            ])->values()->all(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $days
     * @return array{timezone: string, days: list<array<string, mixed>>}
     */
    public function updateBusinessHours(array $days): array
    {
        DB::transaction(function () use ($days) {
            foreach ($days as $dayData) {
                $dayOfWeek = (int) $dayData['day_of_week'];
                $isWorking = (bool) $dayData['is_working_day'];

                BusinessHour::where('day_of_week', $dayOfWeek)->update([
                    'is_working_day' => $isWorking,
                    'opens_at' => $isWorking ? $dayData['opens_at'] : null,
                    'closes_at' => $isWorking ? $dayData['closes_at'] : null,
                ]);
            }
        });

        return $this->getBusinessHours();
    }
}
