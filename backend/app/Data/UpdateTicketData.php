<?php

namespace App\Data;

use App\Enums\TicketPriority;

readonly class UpdateTicketData
{
    public function __construct(
        public int $lockVersion,
        public ?string $subject = null,
        public ?string $description = null,
        public ?int $categoryId = null,
        public ?TicketPriority $priority = null,
        public bool $hasSubject = false,
        public bool $hasDescription = false,
        public bool $hasCategoryId = false,
        public bool $hasPriority = false,
    ) {}
}

