<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentStorage
{
    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'application/csv' => 'csv',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/zip' => 'zip',
    ];

    /**
     * @param list<UploadedFile> $files
     * @return list<StoredFile>
     */
    public function put(array $files, Ticket $ticket): array
    {
        $diskName = config('attachments.disk', 'local');
        $stored = [];

        try {
            foreach ($files as $file) {
                $mimeType = $file->getMimeType() ?: 'application/octet-stream';
                $ext = self::MIME_EXTENSIONS[$mimeType] ?? ($file->guessExtension() ?: 'bin');

                $uuid = (string) Str::uuid();
                $path = "tickets/{$ticket->id}/{$uuid}.{$ext}";

                $clientName = basename($file->getClientOriginalName());
                $cleanedName = preg_replace('/[\x00-\x1F\x7F]/u', '', $clientName) ?: 'attachment.'.$ext;
                $cleanedName = mb_substr($cleanedName, 0, 255);

                $realPath = $file->getRealPath();
                $checksum = hash_file('sha256', $realPath);
                $sizeBytes = $file->getSize();

                $stream = fopen($realPath, 'r');
                Storage::disk($diskName)->put($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                $stored[] = new StoredFile(
                    disk: $diskName,
                    path: $path,
                    originalName: $cleanedName,
                    mimeType: $mimeType,
                    sizeBytes: $sizeBytes,
                    checksumSha256: $checksum,
                );
            }
        } catch (\Throwable $e) {
            $this->delete($stored);
            throw $e;
        }

        return $stored;
    }

    /**
     * @param list<StoredFile|object> $files
     */
    public function delete(array $files): void
    {
        foreach ($files as $file) {
            $disk = $file instanceof StoredFile ? $file->disk : ($file->disk ?? config('attachments.disk', 'local'));
            $path = $file instanceof StoredFile ? $file->path : ($file->path ?? null);
            if ($path) {
                Storage::disk($disk)->delete($path);
            }
        }
    }
}

