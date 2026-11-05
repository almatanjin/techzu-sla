<?php

namespace App\Data;

use App\Enums\SlaStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;

readonly class TicketFilterData
{
    public function __construct(
        public ?string $view = 'active',
        public ?TicketStatus $status = null,
        public ?TicketPriority $priority = null,
        public ?int $categoryId = null,
        public ?string $search = null,
        public ?string $assigned = null,
        public ?SlaStatus $slaStatus = null,
    ) {}
}
