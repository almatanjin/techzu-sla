<?php

namespace App\Services\Tickets;

use App\Data\PostMessageData;
use App\Enums\TicketActivityType;
use App\Enums\TicketMessageType;
use App\Events\TicketMessagePosted;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TicketMessageService
{
    public function __construct(
        private readonly TicketActivityLogger $activityLogger,
        private readonly AttachmentStorage $attachmentStorage,
    ) {}

    public function post(Ticket $ticket, PostMessageData $data, User $author): TicketMessage
    {
        $storedFiles = $this->attachmentStorage->put($data->attachments, $ticket);

        try {
            return DB::transaction(function () use ($ticket, $data, $author, $storedFiles) {
                $message = new TicketMessage([
                    'ticket_id' => $ticket->id,
                    'author_id' => $author->id,
                    'type' => $data->type,
                    'body' => $data->body ?? '',
                ]);
                $message->save();

                foreach ($storedFiles as $stored) {
                    Attachment::create([
                        'ticket_id' => $ticket->id,
                        'ticket_message_id' => $message->id,
                        'uploaded_by_id' => $author->id,
                        'disk' => $stored->disk,
                        'path' => $stored->path,
                        'original_name' => $stored->originalName,
                        'mime_type' => $stored->mimeType,
                        'size_bytes' => $stored->sizeBytes,
                        'checksum_sha256' => $stored->checksumSha256,
                        'created_at' => now(),
                    ]);
                }

                $activityType = $data->type === TicketMessageType::InternalNote
                    ? TicketActivityType::InternalNoteAdded
                    : TicketActivityType::Replied;

                $this->activityLogger->log(
                    ticket: $ticket,
                    type: $activityType,
                    actor: $author,
                    newValues: [
                        'message_id' => $message->id,
                        'attachment_count' => count($storedFiles),
                    ],
                );

                event(new TicketMessagePosted($message, $author));

                return $message->load(['author', 'attachments']);
            });
        } catch (\Throwable $e) {
            $this->attachmentStorage->delete($storedFiles);
            throw $e;
        }
    }
}
