<?php

namespace App\Services\Sla;

use InvalidArgumentException;

final readonly class BusinessSchedule
{
    /**
     * @param  string  $timezone
     * @param  array<int, array{opens: int, closes: int}|null>  $windows  Indexed 0 (Sunday) to 6 (Saturday).
     * @param  array<string, bool>|list<string>  $holidays  Dates formatted Y-m-d.
     */
    public function __construct(
        public string $timezone,
        public array $windows,
        public array $holidays = [],
    ) {
        $hasWorkingDay = false;

        foreach ($this->windows as $dayOfWeek => $window) {
            if ($window === null) {
                continue;
            }

            if (! isset($window['opens'], $window['closes'])) {
                throw new InvalidArgumentException("Window for day {$dayOfWeek} must have opens and closes minutes.");
            }

            if ($window['closes'] <= $window['opens']) {
                throw new InvalidArgumentException("Window for day {$dayOfWeek} must close after opening.");
            }

            $hasWorkingDay = true;
        }

        if (! $hasWorkingDay) {
            throw new InvalidArgumentException('Schedule must have at least one working day.');
        }
    }

    public static function default(string $timezone = 'Asia/Dhaka'): self
    {
        // 0 = Sunday, 1 = Monday, 2 = Tuesday, 3 = Wednesday, 4 = Thursday, 5 = Friday, 6 = Saturday
        // 09:00 = 540 min, 18:00 = 1080 min
        $defaultWindow = ['opens' => 9 * 60, 'closes' => 18 * 60];

        $windows = [
            0 => $defaultWindow,
            1 => $defaultWindow,
            2 => $defaultWindow,
            3 => $defaultWindow,
            4 => $defaultWindow,
            5 => null,
            6 => null,
        ];

        return new self($timezone, $windows, []);
    }

    public function isHoliday(string $dateString): bool
    {
        if (isset($this->holidays[$dateString])) {
            return true;
        }

        return in_array($dateString, $this->holidays, true);
    }

    /**
     * @return array{opens: int, closes: int}|null
     */
    public function windowForDay(int $dayOfWeek): ?array
    {
        return $this->windows[$dayOfWeek] ?? null;
    }
}

