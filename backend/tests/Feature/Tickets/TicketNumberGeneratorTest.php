<?php

use App\Services\Tickets\TicketNumberGenerator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->generator = app(TicketNumberGenerator::class);
});

it('generates the first two numbers of a year', function () {
    $this->travelTo(Carbon::parse('2026-03-01 12:00:00', 'Asia/Dhaka'));

    $number1 = DB::transaction(fn () => $this->generator->next());
    $number2 = DB::transaction(fn () => $this->generator->next());

    expect($number1)->toBe('TKT-2026-000001')
        ->and($number2)->toBe('TKT-2026-000002');
});

it('restarts sequence upon year rollover with travelTo', function () {
    $this->travelTo(Carbon::parse('2026-12-31 23:59:00', 'Asia/Dhaka'));
    $number2026 = DB::transaction(fn () => $this->generator->next());

    expect($number2026)->toBe('TKT-2026-000001');

    $this->travelTo(Carbon::parse('2027-01-01 00:01:00', 'Asia/Dhaka'));
    $number2027 = DB::transaction(fn () => $this->generator->next());

    expect($number2027)->toBe('TKT-2027-000001');
});

it('grows to TKT-2026-1000000 from a preset sequence row past six digits', function () {
    $this->travelTo(Carbon::parse('2026-05-10 10:00:00', 'Asia/Dhaka'));

    DB::table('ticket_sequences')->insert([
        'year' => 2026,
        'last_number' => 999999,
    ]);

    $number = DB::transaction(fn () => $this->generator->next());

    expect($number)->toBe('TKT-2026-1000000');
});

it('honors configured ticket prefix', function () {
    $this->travelTo(Carbon::parse('2026-01-15 10:00:00', 'Asia/Dhaka'));
    config(['tickets.number_prefix' => 'SR']);

    $number = DB::transaction(fn () => $this->generator->next());

    expect($number)->toBe('SR-2026-000001');
});

it('refuses to generate ticket numbers outside of an active database transaction', function () {
    expect(fn () => $this->generator->next())
        ->toThrow(RuntimeException::class);
});

it('produces 50 distinct gap-free numbers over 50 consecutive creations', function () {
    $this->travelTo(Carbon::parse('2026-06-01 10:00:00', 'Asia/Dhaka'));

    $numbers = [];
    for ($i = 0; $i < 50; $i++) {
        $numbers[] = DB::transaction(fn () => $this->generator->next());
    }

    expect(count(array_unique($numbers)))->toBe(50);

    $expected = array_map(fn ($n) => sprintf('TKT-2026-%06d', $n), range(1, 50));
    expect($numbers)->toBe($expected);
});

