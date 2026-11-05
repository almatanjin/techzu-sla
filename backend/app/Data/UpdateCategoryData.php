<?php

namespace App\Data;

readonly class UpdateCategoryData
{
    public function __construct(
        public ?string $name = null,
        public ?bool $isActive = null,
        public bool $hasName = false,
        public bool $hasIsActive = false,
    ) {}
}

