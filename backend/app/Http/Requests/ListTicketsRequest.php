<?php

namespace App\Http\Requests;

use App\Data\TicketFilterData;
use App\Enums\SlaStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Search\TicketSearchTerm;
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
            'search' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (!is_string($value) || trim($value) === '') {
                        return;
                    }
                    $term = TicketSearchTerm::parse($value);
                    if ($term->isKeywords() && empty($term->words)) {
                        $fail('Search for words of at least 3 characters.');
                    }
                },
            ],
            'assigned' => [
                'sometimes',
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === null || $value === '' || $value === 'me' || $value === 'unassigned') {
                        return;
                    }

                    if (!ctype_digit((string) $value)) {
                        $fail('The selected assigned is invalid.');
                        return;
                    }

                    $exists = User::query()
                        ->where('id', (int) $value)
                        ->whereIn('role', [UserRole::Agent, UserRole::Admin])
                        ->exists();

                    if (!$exists) {
                        $fail('The selected assigned is invalid.');
                    }
                },
            ],
            'customer_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Customer->value),
            ],
            'created_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'created_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
            'sla_status' => ['sometimes', 'nullable', 'string', Rule::in(array_map(fn ($s) => $s->value, SlaStatus::cases()))],
            'sort' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    'created_at',
                    '-created_at',
                    'updated_at',
                    '-updated_at',
                    'priority',
                    '-priority',
                    'sla_due_at',
                    '-sla_due_at',
                ]),
            ],
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function toFilterData(): TicketFilterData
    {
        $user = $this->user();
        $isStaff = $user !== null && ($user->isAdmin() || $user->isAgent());

        return new TicketFilterData(
            view: $this->input('view', 'active'),
            status: $this->filled('status') ? TicketStatus::tryFrom($this->input('status')) : null,
            priority: $this->filled('priority') ? TicketPriority::tryFrom($this->input('priority')) : null,
            categoryId: $this->filled('category_id') ? (int) $this->input('category_id') : null,
            search: $this->filled('search') ? trim((string) $this->input('search')) : null,
            assigned: $this->filled('assigned') ? $this->input('assigned') : null,
            customerId: $this->filled('customer_id') ? (int) $this->input('customer_id') : null,
            createdFrom: $this->filled('created_from') ? (string) $this->input('created_from') : null,
            createdTo: $this->filled('created_to') ? (string) $this->input('created_to') : null,
            slaStatus: ($isStaff && $this->filled('sla_status')) ? SlaStatus::tryFrom($this->input('sla_status')) : null,
            sort: $this->filled('sort') ? (string) $this->input('sort') : null,
            perPage: $this->filled('per_page') ? (int) $this->input('per_page') : 20,
        );
    }
}
