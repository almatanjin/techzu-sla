<?php

namespace App\Data;

use App\Enums\UserRole;

readonly class UpdateUserData
{
    /**
     * @param list<int>|null $categoryIds
     */
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?UserRole $role = null,
        public ?string $password = null,
        public ?bool $isActive = null,
        public ?bool $isAvailable = null,
        public ?int $maxOpenTickets = null,
        public ?array $categoryIds = null,
        public bool $hasName = false,
        public bool $hasEmail = false,
        public bool $hasRole = false,
        public bool $hasPassword = false,
        public bool $hasIsActive = false,
        public bool $hasIsAvailable = false,
        public bool $hasMaxOpenTickets = false,
        public bool $hasCategoryIds = false,
    ) {}
}
