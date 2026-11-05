<?php

namespace App\Services\Tickets;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class TicketNumberGenerator
{
    public function next(): string
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Ticket number generation requires an active database transaction.');
        }

        $timezone = config('tickets.timezone', 'Asia/Dhaka');
        $prefix = config('tickets.number_prefix', 'TKT');
        $padding = (int) config('tickets.number_padding', 6);

        $year = (int) now($timezone)->format('Y');

        DB::table('ticket_sequences')->insertOrIgnore([
            'year' => $year,
            'last_number' => 0,
        ]);

        $sequence = DB::table('ticket_sequences')
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        $nextNumber = ((int) $sequence->last_number) + 1;

        DB::table('ticket_sequences')
            ->where('year', $year)
            ->update(['last_number' => $nextNumber]);

        return sprintf('%s-%d-%0*d', $prefix, $year, $padding, $nextNumber);
    }
}

