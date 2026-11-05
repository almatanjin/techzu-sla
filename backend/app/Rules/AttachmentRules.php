<?php

namespace App\Rules;

use Illuminate\Http\UploadedFile;

class AttachmentRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $maxFiles = config('attachments.max_files', 5);
        $maxKb = config('attachments.max_kilobytes', 10240);

        return [
            'attachments' => ['sometimes', 'array', "max:{$maxFiles}"],
            'attachments.*' => ['file', "max:{$maxKb}", new AllowedAttachment],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        $maxFiles = config('attachments.max_files', 5);

        return [
            'attachments.max' => "You can attach up to {$maxFiles} files.",
            'attachments.*.max' => ':attribute must not be larger than 10 MB.',
        ];
    }

    /**
     * @param array<int|string, mixed> $files
     * @return array<string, string>
     */
    public static function attributes(array $files = []): array
    {
        $attributes = [];

        foreach ($files as $index => $file) {
            if ($file instanceof UploadedFile) {
                $attributes["attachments.{$index}"] = $file->getClientOriginalName();
            }
        }

        return $attributes;
    }
}
