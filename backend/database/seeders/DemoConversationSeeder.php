<?php

namespace Database\Seeders;

use App\Enums\TicketActivityType;
use App\Enums\TicketMessageType;
use App\Enums\UserRole;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\Tickets\AttachmentStorage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DemoConversationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $diskName = config('attachments.disk', 'local');
        Storage::disk($diskName)->deleteDirectory('tickets');

        $tickets = Ticket::with(['customer', 'assignedAgent'])->get();
        if ($tickets->isEmpty()) {
            return;
        }

        $agents = User::where('role', UserRole::Agent)->get();
        if ($agents->isEmpty()) {
            return;
        }

        $attachmentStorage = app(AttachmentStorage::class);

        // Generate files
        $pdfBytes = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000052 00000 n \n0000000108 00000 n \ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n185\n%%EOF";

        $im = imagecreatetruecolor(10, 10);
        $bg = imagecolorallocate($im, 70, 130, 180);
        imagefill($im, 0, 0, $bg);
        ob_start();
        imagepng($im);
        $pngBytes = (string) ob_get_clean();
        imagedestroy($im);

        $txtBytes = "1. Log in to dashboard\n2. Navigate to Settings\n3. Click Update Profile\n";

        $seededFilesCount = 0;

        foreach ($tickets as $index => $ticket) {
            // Seed conversation on about a third of tickets
            if ($index % 3 !== 0) {
                continue;
            }

            $customer = $ticket->customer;
            $agent = $ticket->assignedAgent ?: $agents[$index % $agents->count()];
            $createdAt = CarbonImmutable::parse($ticket->created_at);

            // On the very first conversation ticket, attach summary.pdf to the ticket itself
            if ($seededFilesCount === 0) {
                $tempPdf = tempnam(sys_get_temp_dir(), 'pdf');
                file_put_contents($tempPdf, $pdfBytes);
                $uploadedPdf = new UploadedFile($tempPdf, 'summary.pdf', 'application/pdf', null, true);
                $storedPdfList = $attachmentStorage->put([$uploadedPdf], $ticket);
                foreach ($storedPdfList as $stored) {
                    Attachment::create([
                        'ticket_id' => $ticket->id,
                        'ticket_message_id' => null,
                        'uploaded_by_id' => $customer->id,
                        'disk' => $stored->disk,
                        'path' => $stored->path,
                        'original_name' => $stored->originalName,
                        'mime_type' => $stored->mimeType,
                        'size_bytes' => $stored->sizeBytes,
                        'checksum_sha256' => $stored->checksumSha256,
                        'created_at' => $createdAt,
                    ]);
                }
                @unlink($tempPdf);
            }

            // Message 1: Customer reply
            $msg1Time = $createdAt->addMinutes(10);
            $msg1 = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'author_id' => $customer->id,
                'type' => TicketMessageType::Reply,
                'body' => 'Hello, I wanted to follow up on this ticket regarding the recent update.',
                'created_at' => $msg1Time,
            ]);
            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $customer->id,
                'type' => TicketActivityType::Replied,
                'old_values' => null,
                'new_values' => ['message_id' => $msg1->id, 'attachment_count' => 0],
                'created_at' => $msg1Time,
            ]);

            // Message 2: Agent reply (with steps.txt on the first conversation ticket)
            $msg2Time = $createdAt->addMinutes(25);
            $msg2 = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'author_id' => $agent->id,
                'type' => TicketMessageType::Reply,
                'body' => 'Thank you for reaching out. We have investigated the issue and found the resolution steps.',
                'created_at' => $msg2Time,
            ]);

            $msg2AttCount = 0;
            if ($seededFilesCount === 0) {
                $tempTxt = tempnam(sys_get_temp_dir(), 'txt');
                file_put_contents($tempTxt, $txtBytes);
                $uploadedTxt = new UploadedFile($tempTxt, 'steps.txt', 'text/plain', null, true);
                $storedTxtList = $attachmentStorage->put([$uploadedTxt], $ticket);
                foreach ($storedTxtList as $stored) {
                    Attachment::create([
                        'ticket_id' => $ticket->id,
                        'ticket_message_id' => $msg2->id,
                        'uploaded_by_id' => $agent->id,
                        'disk' => $stored->disk,
                        'path' => $stored->path,
                        'original_name' => $stored->originalName,
                        'mime_type' => $stored->mimeType,
                        'size_bytes' => $stored->sizeBytes,
                        'checksum_sha256' => $stored->checksumSha256,
                        'created_at' => $msg2Time,
                    ]);
                }
                @unlink($tempTxt);
                $msg2AttCount = 1;
            }

            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $agent->id,
                'type' => TicketActivityType::Replied,
                'old_values' => null,
                'new_values' => ['message_id' => $msg2->id, 'attachment_count' => $msg2AttCount],
                'created_at' => $msg2Time,
            ]);

            // Message 3: Agent internal note (on at least the first ticket and some others)
            $msg3Time = $createdAt->addMinutes(35);
            $msg3 = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'author_id' => $agent->id,
                'type' => TicketMessageType::InternalNote,
                'body' => 'Internal note: Customer account has legacy subscription configuration.',
                'created_at' => $msg3Time,
            ]);

            $msg3AttCount = 0;
            if ($seededFilesCount === 0) {
                $tempPng = tempnam(sys_get_temp_dir(), 'png');
                file_put_contents($tempPng, $pngBytes);
                $uploadedPng = new UploadedFile($tempPng, 'screen.png', 'image/png', null, true);
                $storedPngList = $attachmentStorage->put([$uploadedPng], $ticket);
                foreach ($storedPngList as $stored) {
                    Attachment::create([
                        'ticket_id' => $ticket->id,
                        'ticket_message_id' => $msg3->id,
                        'uploaded_by_id' => $agent->id,
                        'disk' => $stored->disk,
                        'path' => $stored->path,
                        'original_name' => $stored->originalName,
                        'mime_type' => $stored->mimeType,
                        'size_bytes' => $stored->sizeBytes,
                        'checksum_sha256' => $stored->checksumSha256,
                        'created_at' => $msg3Time,
                    ]);
                }
                @unlink($tempPng);
                $msg3AttCount = 1;
                $seededFilesCount = 3;
            }

            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $agent->id,
                'type' => TicketActivityType::InternalNoteAdded,
                'old_values' => null,
                'new_values' => ['message_id' => $msg3->id, 'attachment_count' => $msg3AttCount],
                'created_at' => $msg3Time,
            ]);

            // Additional customer reply
            $msg4Time = $createdAt->addMinutes(50);
            $msg4 = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'author_id' => $customer->id,
                'type' => TicketMessageType::Reply,
                'body' => 'I followed the steps and everything works now. Thank you!',
                'created_at' => $msg4Time,
            ]);
            TicketActivity::create([
                'ticket_id' => $ticket->id,
                'actor_id' => $customer->id,
                'type' => TicketActivityType::Replied,
                'old_values' => null,
                'new_values' => ['message_id' => $msg4->id, 'attachment_count' => 0],
                'created_at' => $msg4Time,
            ]);
        }
    }
}
