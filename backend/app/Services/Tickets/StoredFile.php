<?php

namespace App\Services\Tickets;

readonly class StoredFile
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $originalName,
        public string $mimeType,
        public int $sizeBytes,
        public string $checksumSha256,
    ) {}
}

