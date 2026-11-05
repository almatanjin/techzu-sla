<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return $ticket instanceof Ticket && ($this->user()?->can('assign', $ticket) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agent_id' => [
                'present',
                'nullable',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $user = User::query()->find($value);
                    if (! $user || ! $user->isAgent() || ! (bool) $user->is_active || ! (bool) $user->is_available) {
                        $fail('The selected agent must be an active, available agent.');
                    }
                },
            ],
            'lock_version' => ['required', 'integer'],
        ];
    }
}

