<?php

namespace App\Data;

readonly class CreateCategoryData
{
    public function __construct(
        public string $name,
        public bool $isActive = true,
    ) {}
}

