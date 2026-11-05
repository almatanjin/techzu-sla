<?php

namespace App\Imports;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Carbon\CarbonImmutable;

class ImportRow
{
    public function __construct(
        public int $line,
        public string $externalReference,
        public string $customerEmail,
        public string $subject,
        public string $description,
        public TicketPriority $priority,
        public TicketStatus $status,
        public string $categoryName,
        public ?CarbonImmutable $createdAt = null,
        public ?CarbonImmutable $resolvedAt = null,
        public ?CarbonImmutable $closedAt = null,
        public array $rawRow = [],
    ) {}
}

