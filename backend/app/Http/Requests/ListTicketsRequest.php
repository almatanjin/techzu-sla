<?php

namespace App\Http\Requests;

use App\Data\TicketFilterData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (bool) $this->user()->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'view' => ['sometimes', 'string', Rule::in(['active', 'closed', 'all'])],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(array_map(fn ($s) => $s->value, TicketStatus::cases()))],
            'priority' => ['sometimes', 'nullable', 'string', Rule::in(array_map(fn ($p) => $p->value, TicketPriority::cases()))],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'assigned' => ['sometimes', 'nullable', 'string', Rule::in(['me', 'unassigned'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function toFilterData(): TicketFilterData
    {
        return new TicketFilterData(
            view: $this->input('view', 'active'),
            status: $this->filled('status') ? TicketStatus::tryFrom($this->input('status')) : null,
            priority: $this->filled('priority') ? TicketPriority::tryFrom($this->input('priority')) : null,
            categoryId: $this->filled('category_id') ? (int) $this->input('category_id') : null,
            search: $this->filled('search') ? trim((string) $this->input('search')) : null,
            assigned: $this->filled('assigned') ? $this->input('assigned') : null,
        );
    }
}

