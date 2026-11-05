<?php

namespace App\Data;

use App\Enums\TicketStatus;

readonly class ChangeStatusData
{
    public function __construct(
        public TicketStatus $status,
        public int $lockVersion,
    ) {}
}

