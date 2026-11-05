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
        public ?int $customerId = null,
        public ?string $createdFrom = null,
        public ?string $createdTo = null,
        public ?SlaStatus $slaStatus = null,
        public ?string $sort = null,
        public ?int $perPage = 20,
    ) {}
}
