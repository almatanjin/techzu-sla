<?php

namespace App\Policies;

use App\Enums\TicketMessageType;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AttachmentPolicy
{
    public function download(User $user, Attachment $attachment): Response
    {
        if (! (bool) $user->is_active) {
            return Response::deny();
        }

        $ticket = $attachment->ticket;
        if (! $ticket) {
            return Response::denyAsNotFound();
        }

        if (! ($user->isAdmin() || $user->isAgent() || ($user->isCustomer() && $ticket->customer_id === $user->id))) {
            return Response::denyAsNotFound();
        }

        if ($user->isAdmin() || $user->isAgent()) {
            return Response::allow();
        }

        if ($attachment->ticket_message_id === null) {
            return Response::allow();
        }

        $message = $attachment->message;
        if ($message && $message->type === TicketMessageType::Reply) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }
}

