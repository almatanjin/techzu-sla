<?php

namespace App\Services\Sla;

use App\Models\BusinessHour;
use App\Models\Holiday;

class BusinessScheduleRepository
{
    public function current(): BusinessSchedule
    {
        $timezone = (string) config('tickets.timezone', 'Asia/Dhaka');
        $hours = BusinessHour::orderBy('day_of_week')->get();

        $windows = [];
        for ($i = 0; $i <= 6; $i++) {
            $windows[$i] = null;
        }

        foreach ($hours as $hour) {
            if (! $hour->is_working_day || ! $hour->opens_at || ! $hour->closes_at) {
                $windows[$hour->day_of_week] = null;
                continue;
            }

            $opensParts = explode(':', (string) $hour->opens_at);
            $closesParts = explode(':', (string) $hour->closes_at);

            $opensMinutes = ((int) $opensParts[0]) * 60 + ((int) ($opensParts[1] ?? 0));
            $closesMinutes = ((int) $closesParts[0]) * 60 + ((int) ($closesParts[1] ?? 0));

            $windows[$hour->day_of_week] = [
                'opens' => $opensMinutes,
                'closes' => $closesMinutes,
            ];
        }

        $holidays = Holiday::query()
            ->pluck('date')
            ->map(fn ($date) => is_string($date) ? $date : $date->format('Y-m-d'))
            ->all();

        return new BusinessSchedule($timezone, $windows, $holidays);
    }
}
