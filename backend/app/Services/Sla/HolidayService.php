<?php

namespace App\Services\Sla;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Collection;

class HolidayService
{
    /**
     * @return Collection<int, Holiday>
     */
    public function listHolidays(?int $year = null): Collection
    {
        if ($year === null) {
            $year = (int) now(config('tickets.timezone', 'Asia/Dhaka'))->format('Y');
        }

        return Holiday::whereYear('date', $year)
            ->orderBy('date')
            ->get();
    }

    public function createHoliday(string $date, string $name): Holiday
    {
        return Holiday::create([
            'date' => $date,
            'name' => $name,
        ]);
    }

    public function deleteHoliday(Holiday $holiday): void
    {
        $holiday->delete();
    }
}

