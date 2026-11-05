<?php

namespace App\Listeners;

use App\Enums\TicketMessageType;
use App\Enums\UserRole;
use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Events\TicketEscalated;
use App\Events\TicketMessagePosted;
use App\Events\TicketNeedsAgent;
use App\Events\TicketResolved;
use App\Events\TicketSlaWarningReached;
use App\Models\User;
use App\Notifications\TicketNotificationRecipients;
use App\Notifications\Tickets\CustomerRepliedNotification;
use App\Notifications\Tickets\InternalNoteAddedNotification;
use App\Notifications\Tickets\SlaWarningNotification;
use App\Notifications\Tickets\StaffRepliedNotification;
use App\Notifications\Tickets\TicketAssignedNotification;
use App\Notifications\Tickets\TicketCreatedNotification;
use App\Notifications\Tickets\TicketEscalatedNotification;
use App\Notifications\Tickets\TicketNeedsAgentNotification;
use App\Notifications\Tickets\TicketResolvedNotification;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;

class SendTicketNotifications
{
    public function handleTicketCreated(TicketCreated $event): void
    {
        $ticket = $event->ticket;
        $customer = $ticket->customer ?? User::find($ticket->customer_id);

        $recipients = TicketNotificationRecipients::filter([$customer]);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TicketCreatedNotification($ticket));
        }
    }

    public function handleTicketAssigned(TicketAssigned $event): void
    {
        $recipients = TicketNotificationRecipients::filter([$event->agent], exclude: $event->actor);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TicketAssignedNotification($event->ticket));
        }
    }

    public function handleTicketNeedsAgent(TicketNeedsAgent $event): void
    {
        $admins = User::query()->where('role', UserRole::Admin)->where('is_active', true)->get();
        $recipients = TicketNotificationRecipients::filter($admins);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TicketNeedsAgentNotification($event->ticket));
        }
    }

    public function handleTicketMessagePosted(TicketMessagePosted $event): void
    {
        $message = $event->message;
        $author = $event->author;
        $ticket = $message->ticket;

        if ($message->type === TicketMessageType::InternalNote) {
            $assignee = $ticket->assignedAgent;
            $recipients = TicketNotificationRecipients::filter([$assignee], exclude: $author);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new InternalNoteAddedNotification($ticket, $message));
            }

            return;
        }

        if ($author->isStaff()) {
            $customer = $ticket->customer ?? User::find($ticket->customer_id);
            $recipients = TicketNotificationRecipients::filter([$customer], exclude: $author);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new StaffRepliedNotification($ticket, $message));
            }
        } else {
            $candidates = $ticket->assigned_agent_id !== null
                ? [$ticket->assignedAgent]
                : User::query()->where('role', UserRole::Admin)->where('is_active', true)->get();

            $recipients = TicketNotificationRecipients::filter($candidates, exclude: $author);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new CustomerRepliedNotification($ticket, $message));
            }
        }
    }

    public function handleTicketResolved(TicketResolved $event): void
    {
        $ticket = $event->ticket;
        $customer = $ticket->customer ?? User::find($ticket->customer_id);

        $recipients = TicketNotificationRecipients::filter([$customer], exclude: $event->actor);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TicketResolvedNotification($ticket));
        }
    }

    public function handleTicketSlaWarningReached(TicketSlaWarningReached $event): void
    {
        $ticket = $event->ticket;
        $candidates = $ticket->assigned_agent_id !== null
            ? [$ticket->assignedAgent]
            : User::query()->where('role', UserRole::Admin)->where('is_active', true)->get();

        $recipients = TicketNotificationRecipients::filter($candidates);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new SlaWarningNotification($ticket));
        }
    }

    public function handleTicketEscalated(TicketEscalated $event): void
    {
        $admins = User::query()->where('role', UserRole::Admin)->where('is_active', true)->get();
        $candidates = array_filter([$event->formerAssignee, ...$admins]);

        $recipients = TicketNotificationRecipients::filter($candidates);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TicketEscalatedNotification($event->ticket, $event->formerAssignee));
        }
    }
}
