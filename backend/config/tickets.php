<?php

return [
    'number_prefix' => env('TICKET_NUMBER_PREFIX', 'TKT'),
    'number_padding' => (int) env('TICKET_NUMBER_PADDING', 6),
    'timezone' => env('TICKETS_TIMEZONE', 'Asia/Dhaka'),
];

