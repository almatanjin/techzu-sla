<?php

namespace App\Http\Requests;

use App\Data\PostMessageData;
use App\Enums\TicketMessageType;
use App\Rules\AttachmentRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (bool) $this->user()->is_active;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('body')) {
            $body = $this->input('body');
            $trimmed = is_string($body) ? trim($body) : $body;
            $this->merge([
                'body' => $trimmed === '' ? null : $trimmed,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $attachmentRules = AttachmentRules::rules();

        return [
            'type' => ['sometimes', Rule::enum(TicketMessageType::class)],
            'body' => ['required_without:attachments', 'nullable', 'string', 'max:10000'],
            'attachments' => $attachmentRules['attachments'],
            'attachments.*' => $attachmentRules['attachments.*'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return AttachmentRules::messages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $files = $this->file('attachments', []);
        if ($files instanceof \Illuminate\Http\UploadedFile) {
            $files = [$files];
        }

        return AttachmentRules::attributes(is_array($files) ? $files : []);
    }

    public function toData(): PostMessageData
    {
        $type = $this->validated('type');
        $attachments = $this->file('attachments', []);
        if ($attachments instanceof \Illuminate\Http\UploadedFile) {
            $attachments = [$attachments];
        }

        return new PostMessageData(
            body: $this->validated('body'),
            type: $type ? TicketMessageType::from($type) : TicketMessageType::Reply,
            attachments: is_array($attachments) ? $attachments : [],
        );
    }
}
