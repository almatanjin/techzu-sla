<?php

namespace App\Data;

use App\Enums\TicketPriority;
use Illuminate\Http\UploadedFile;

readonly class CreateTicketData
{
    /**
     * @param list<UploadedFile> $attachments
     */
    public function __construct(
        public string $subject,
        public string $description,
        public int $categoryId,
        public int $customerId,
        public TicketPriority $priority = TicketPriority::Medium,
        public array $attachments = [],
    ) {}
}

