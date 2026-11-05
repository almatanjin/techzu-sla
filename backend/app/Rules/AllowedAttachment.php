<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class AllowedAttachment implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('The :attribute must be a file.');

            return;
        }

        $clientName = $value->getClientOriginalName();
        $maxKb = config('attachments.max_kilobytes', 10240);

        if ($value->getSize() > $maxKb * 1024) {
            $fail("{$clientName} must not be larger than 10 MB.");

            return;
        }

        $types = config('attachments.types', []);
        $allowedExtList = implode(', ', array_keys($types));

        $ext = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));

        if (! array_key_exists($ext, $types)) {
            $fail("{$clientName} must be one of these types: {$allowedExtList}.");

            return;
        }

        $detectedMime = (string) $value->getMimeType();
        $allowedMimes = $types[$ext];

        if (! in_array($detectedMime, $allowedMimes, true)) {
            $fail("{$clientName} must be one of these types: {$allowedExtList}.");

            return;
        }
    }
}

