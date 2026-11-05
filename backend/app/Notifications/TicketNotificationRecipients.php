<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Support\Collection;

class TicketNotificationRecipients
{
    /**
     * @param  iterable<User|null>|Collection<int, User|null>  $users
     * @return Collection<int, User>
     */
    public static function filter(iterable $users, ?User $exclude = null): Collection
    {
        $filtered = collect($users)
            ->filter(function (?User $user) use ($exclude): bool {
                if ($user === null) {
                    return false;
                }

                if (! $user->is_active) {
                    return false;
                }

                if ($exclude !== null && $user->id === $exclude->id) {
                    return false;
                }

                return true;
            })
            ->unique('id')
            ->values();

        return $filtered;
    }
}

