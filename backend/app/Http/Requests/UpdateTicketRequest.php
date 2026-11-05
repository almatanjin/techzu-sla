<?php

namespace App\Http\Requests;

use App\Data\UpdateTicketData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || ! $user->is_active) {
            return false;
        }

        $ticket = $this->route('ticket');
        if (! ($ticket instanceof Ticket)) {
            return false;
        }

        // If ticket is closed, no edits allowed
        if ($ticket->status === TicketStatus::Closed) {
            return false;
        }

        $hasContent = $this->has('subject') || $this->has('description');
        $hasClassification = $this->has('priority') || $this->has('category_id');

        if ($hasContent && ! $user->can('updateContent', $ticket)) {
            return false;
        }

        if ($hasClassification && ! $user->can('updateClassification', $ticket)) {
            return false;
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('subject') && is_string($this->input('subject'))) {
            $this->merge(['subject' => trim($this->input('subject'))]);
        }
        if ($this->has('description') && is_string($this->input('description'))) {
            $this->merge(['description' => trim($this->input('description'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer'],
            'subject' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'string', 'max:10000'],
            'category_id' => [
                'sometimes',
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'priority' => ['sometimes', 'string', Rule::enum(TicketPriority::class)],
        ];
    }

    public function toData(): UpdateTicketData
    {
        $hasSubject = $this->has('subject');
        $hasDescription = $this->has('description');
        $hasCategoryId = $this->has('category_id');
        $hasPriority = $this->has('priority');

        $priority = $hasPriority && $this->validated('priority') !== null
            ? TicketPriority::from((string) $this->validated('priority'))
            : null;

        return new UpdateTicketData(
            lockVersion: (int) $this->validated('lock_version'),
            subject: $hasSubject ? (string) $this->validated('subject') : null,
            description: $hasDescription ? (string) $this->validated('description') : null,
            categoryId: $hasCategoryId ? (int) $this->validated('category_id') : null,
            priority: $priority,
            hasSubject: $hasSubject,
            hasDescription: $hasDescription,
            hasCategoryId: $hasCategoryId,
            hasPriority: $hasPriority,
        );
    }
}

