<?php

namespace App\Data;

use App\Enums\TicketMessageType;
use Illuminate\Http\UploadedFile;

readonly class PostMessageData
{
    /**
     * @param list<UploadedFile> $attachments
     */
    public function __construct(
        public ?string $body,
        public TicketMessageType $type = TicketMessageType::Reply,
        public array $attachments = [],
    ) {}
}

