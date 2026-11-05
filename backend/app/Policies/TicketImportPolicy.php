<?php

namespace App\Policies;

use App\Models\TicketImport;
use App\Models\User;

class TicketImportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && (bool) $user->is_active;
    }

    public function view(User $user, TicketImport $ticketImport): bool
    {
        return $user->isAdmin() && (bool) $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() && (bool) $user->is_active;
    }

    public function downloadErrors(User $user, TicketImport $ticketImport): bool
    {
        return $user->isAdmin() && (bool) $user->is_active;
    }

    public function downloadTemplate(User $user): bool
    {
        return $user->isAdmin() && (bool) $user->is_active;
    }
}

