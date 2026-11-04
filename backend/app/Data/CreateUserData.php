<?php

namespace App\Data;

use App\Enums\UserRole;

readonly class CreateUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public UserRole $role,
        public string $password,
        public bool $isActive = true,
    ) {}
}

