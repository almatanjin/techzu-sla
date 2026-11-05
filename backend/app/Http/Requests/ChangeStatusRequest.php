<?php

namespace App\Http\Requests;

use App\Data\ChangeStatusData;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::enum(TicketStatus::class)],
            'lock_version' => ['required', 'integer'],
        ];
    }

    public function toData(): ChangeStatusData
    {
        return new ChangeStatusData(
            status: TicketStatus::from((string) $this->validated('status')),
            lockVersion: (int) $this->validated('lock_version'),
        );
    }
}

