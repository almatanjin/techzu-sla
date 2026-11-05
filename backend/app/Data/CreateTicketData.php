<?php

namespace App\Data;

use App\Enums\TicketPriority;

readonly class CreateTicketData
{
    public function __construct(
        public string $subject,
        public string $description,
        public int $categoryId,
        public int $customerId,
        public TicketPriority $priority = TicketPriority::Medium,
    ) {}
}

