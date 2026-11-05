<?php

namespace App\Http\Requests;

use App\Data\CreateTicketData;
use App\Enums\TicketPriority;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (bool) $this->user()->is_active;
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
        $user = $this->user();
        $isStaff = $user !== null && ($user->isAdmin() || $user->isAgent());

        $rules = [
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:10000'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'priority' => ['sometimes', 'string', Rule::enum(TicketPriority::class)],
        ];

        if ($isStaff) {
            $rules['customer_id'] = [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('role', UserRole::Customer->value)
                    ->where('is_active', true),
            ];
        }

        return $rules;
    }

    public function toData(): CreateTicketData
    {
        $user = $this->user();
        $customerId = ($user !== null && $user->isCustomer())
            ? $user->id
            : (int) $this->validated('customer_id');

        $priority = $this->has('priority') && $this->validated('priority') !== null
            ? TicketPriority::from((string) $this->validated('priority'))
            : TicketPriority::Medium;

        return new CreateTicketData(
            subject: (string) $this->validated('subject'),
            description: (string) $this->validated('description'),
            categoryId: (int) $this->validated('category_id'),
            customerId: $customerId,
            priority: $priority,
        );
    }
}

