<?php

namespace App\Data;

use App\Enums\UserRole;

readonly class UserFilterData
{
    public function __construct(
        public ?UserRole $role = null,
        public ?bool $isActive = null,
        public ?string $search = null,
    ) {}
}

