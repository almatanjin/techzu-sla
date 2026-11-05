<?php

use App\Services\Sla\BusinessHoursCalculator;
use App\Services\Sla\BusinessSchedule;
use Carbon\CarbonImmutable;

test('under default schedule Sunday 10:00 plus 120 minutes is Sunday 12:00', function () {
    $schedule = BusinessSchedule::default();
    $calculator = new BusinessHoursCalculator($schedule);

    $start = CarbonImmutable::parse('2026-11-08 10:00:00', 'Asia/Dhaka');
    $due = $calculator->addBusinessMinutes($start, 120);

    expect($due->getTimezone()->getName())->toBe('UTC')
        ->and($due->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))->toBe('2026-11-08 12:00');
});

test('Thursday 17:00 plus 2 hours is Sunday 10:00 under default schedule', function () {
    $schedule = BusinessSchedule::default();
    $calculator = new BusinessHoursCalculator($schedule);

    $start = CarbonImmutable::parse('2026-11-05 17:00:00', 'Asia/Dhaka');
    $due = $calculator->addBusinessMinutes($start, 120);

    expect($due->getTimezone()->getName())->toBe('UTC')
        ->and($due->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))->toBe('2026-11-08 10:00');
});


test('Friday 11:00 plus 2 hours is Sunday 11:00 under default schedule', function () {
    $schedule = BusinessSchedule::default();
    $calculator = new BusinessHoursCalculator($schedule);

    $start = CarbonImmutable::parse('2026-11-06 11:00:00', 'Asia/Dhaka');
    $due = $calculator->addBusinessMinutes($start, 120);

    expect($due->getTimezone()->getName())->toBe('UTC')
        ->and($due->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))->toBe('2026-11-08 11:00');
});


test('Sunday 08:00 plus 8 hours is Sunday 17:00 under default schedule', function () {
    $schedule = BusinessSchedule::default();
    $calculator = new BusinessHoursCalculator($schedule);

    $start = CarbonImmutable::parse('2026-11-08 08:00:00', 'Asia/Dhaka');
    $due = $calculator->addBusinessMinutes($start, 480);

    expect($due->getTimezone()->getName())->toBe('UTC')
        ->and($due->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))->toBe('2026-11-08 17:00');
});


test('Sunday 17:00 plus 1 hour is Sunday 18:00 under default schedule', function () {
    $schedule = BusinessSchedule::default();
    $calculator = new BusinessHoursCalculator($schedule);

    $start = CarbonImmutable::parse('2026-11-08 17:00:00', 'Asia/Dhaka');
    $due = $calculator->addBusinessMinutes($start, 60);

    expect($due->getTimezone()->getName())->toBe('UTC')
        ->and($due->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))->toBe('2026-11-08 18:00');
});


test('Thursday 18:00 plus 1 hour is Sunday 10:00 under default schedule', function () {
    $schedule = BusinessSchedule::default();
    $calculator = new BusinessHoursCalculator($schedule);

    $start = CarbonImmutable::parse('2026-11-05 18:00:00', 'Asia/Dhaka');
    $due = $calculator->addBusinessMinutes($start, 60);

    expect($due->getTimezone()->getName())->toBe('UTC')
        ->and($due->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))->toBe('2026-11-08 10:00');
});


test('adding 0 minutes returns the start', function () {
    $schedule = BusinessSchedule::default();
    $calculator = new BusinessHoursCalculator($schedule);

    $start = CarbonImmutable::parse('2026-11-08 10:15:00', 'Asia/Dhaka');
    $due = $calculator->addBusinessMinutes($start, 0);

    expect($due->getTimezone()->getName())->toBe('UTC')
        ->and($due->setTimezone('Asia/Dhaka')->format('Y-m-d H:i:s'))->toBe('2026-11-08 10:15:00');
});


test('Monday 10:00 plus 24 hours is Wednesday 16:00 under default schedule', function () {
    $schedule = BusinessSchedule::default();
    $calculator = new BusinessHoursCalculator($schedule);

    $start = CarbonImmutable::parse('2026-11-09 10:00:00', 'Asia/Dhaka');
    $due = $calculator->addBusinessMinutes($start, 24 * 60);

    expect($due->getTimezone()->getName())->toBe('UTC')
        ->and($due->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))->toBe('2026-11-11 16:00');
});

test('Sunday 09:00 plus 48 hours is next Sunday 12:00 under default schedule', function () {
    $schedule = BusinessSchedule::default();
    $calculator = new BusinessHoursCalculator($schedule);

    $start = CarbonImmutable::parse('2026-11-08 09:00:00', 'Asia/Dhaka');
    $due = $calculator->addBusinessMinutes($start, 48 * 60);

    expect($due->getTimezone()->getName())->toBe('UTC')
        ->and($due->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))->toBe('2026-11-15 12:00');
});

test('Wednesday 17:30 plus 2 hours with Thursday a holiday is Sunday 10:30', function () {
    $default = BusinessSchedule::default();
    $schedule = new BusinessSchedule(
        timezone: $default->timezone,
        windows: $default->windows,
        holidays: ['2026-11-12'],
    );
    $calculator = new BusinessHoursCalculator($schedule);

    $start = CarbonImmutable::parse('2026-11-11 17:30:00', 'Asia/Dhaka');
    $due = $calculator->addBusinessMinutes($start, 120);

    expect($due->getTimezone()->getName())->toBe('UTC')
        ->and($due->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))->toBe('2026-11-15 10:30');
});

test('a day with its own hours is respected', function () {
    $default = BusinessSchedule::default();
    $windows = $default->windows;
    // Tuesday is day 2: 10:00 (600) to 14:00 (840)
    $windows[2] = ['opens' => 10 * 60, 'closes' => 14 * 60];

    $schedule = new BusinessSchedule(
        timezone: $default->timezone,
        windows: $windows,
    );
    $calculator = new BusinessHoursCalculator($schedule);

    // Monday 17:00 + 3 hours (60 min on Mon + 120 min on Tue starting at 10:00 -> 12:00)
    $start = CarbonImmutable::parse('2026-11-09 17:00:00', 'Asia/Dhaka');
    $due = $calculator->addBusinessMinutes($start, 180);

    expect($due->getTimezone()->getName())->toBe('UTC')
        ->and($due->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'))->toBe('2026-11-10 12:00');
});


test('Thursday 11:00 UTC plus 2 hours is Sunday 04:00 UTC with Asia/Dhaka', function () {
    $schedule = BusinessSchedule::default('Asia/Dhaka');
    $calculator = new BusinessHoursCalculator($schedule);

    $start = CarbonImmutable::parse('2026-11-05 11:00:00', 'UTC');
    $due = $calculator->addBusinessMinutes($start, 120);

    expect($due->getTimezone()->getName())->toBe('UTC')
        ->and($due->format('Y-m-d H:i'))->toBe('2026-11-08 04:00');
});

test('a schedule with no working day is rejected', function () {
    expect(fn () => new BusinessSchedule('Asia/Dhaka', [
        0 => null, 1 => null, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null,
    ]))->toThrow(InvalidArgumentException::class, 'Schedule must have at least one working day.');
});

test('a window closing at or before its opening is rejected', function () {
    expect(fn () => new BusinessSchedule('Asia/Dhaka', [
        0 => ['opens' => 18 * 60, 'closes' => 9 * 60],
    ]))->toThrow(InvalidArgumentException::class, 'Window for day 0 must close after opening.');

    expect(fn () => new BusinessSchedule('Asia/Dhaka', [
        0 => ['opens' => 9 * 60, 'closes' => 9 * 60],
    ]))->toThrow(InvalidArgumentException::class, 'Window for day 0 must close after opening.');
});
