<?php

namespace App\Data;

use App\Enums\UserRole;

readonly class UpdateUserData
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?UserRole $role = null,
        public ?string $password = null,
        public ?bool $isActive = null,
        public bool $hasName = false,
        public bool $hasEmail = false,
        public bool $hasRole = false,
        public bool $hasPassword = false,
        public bool $hasIsActive = false,
    ) {}
}

