<?php

namespace App\Services\Sla;

use Carbon\CarbonImmutable;
use RuntimeException;

final readonly class BusinessHoursCalculator
{
    private const MAX_DAYS_SEARCH = 3660;

    public function __construct(
        private BusinessSchedule $schedule,
    ) {}

    public function addBusinessMinutes(CarbonImmutable $start, int $minutes): CarbonImmutable
    {
        if ($minutes < 0) {
            throw new \InvalidArgumentException('Minutes to add must be non-negative.');
        }

        if ($minutes === 0) {
            return $start->setTimezone('UTC');
        }

        $cursor = $start->setTimezone($this->schedule->timezone);
        $remainingMinutes = $minutes;
        $daysSearched = 0;

        while ($remainingMinutes > 0) {
            if ($daysSearched > self::MAX_DAYS_SEARCH) {
                throw new RuntimeException('Exceeded maximum search window for business hours.');
            }

            $dayOfWeek = $cursor->dayOfWeek;
            $dateString = $cursor->format('Y-m-d');
            $window = $this->schedule->windowForDay($dayOfWeek);
            $isHoliday = $this->schedule->isHoliday($dateString);

            $cursorMinute = $cursor->hour * 60 + $cursor->minute;

            if ($window === null || $isHoliday || $cursorMinute >= $window['closes']) {
                $cursor = $cursor->addDay()->startOfDay();
                $daysSearched++;
                continue;
            }

            if ($cursorMinute < $window['opens']) {
                $cursor = $cursor->setTime(intdiv($window['opens'], 60), $window['opens'] % 60, 0);
                $cursorMinute = $window['opens'];
            }

            $availableToday = $window['closes'] - $cursorMinute;

            if ($remainingMinutes <= $availableToday) {
                $cursor = $cursor->addMinutes($remainingMinutes);
                return $cursor->setTimezone('UTC');
            }

            $remainingMinutes -= $availableToday;
            $cursor = $cursor->addDay()->startOfDay();
            $daysSearched++;
        }

        return $cursor->setTimezone('UTC');
    }
}

