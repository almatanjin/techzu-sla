<?php

namespace App\Http\Requests\Admin;

use App\Data\UpdateSlaPolicyData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSlaPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAdmin() && (bool) $this->user()->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resolution_minutes' => ['sometimes', 'integer', 'min:15', 'max:43200'],
            'warning_threshold_percent' => ['sometimes', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function toData(): UpdateSlaPolicyData
    {
        return new UpdateSlaPolicyData(
            resolutionMinutes: $this->has('resolution_minutes') ? (int) $this->input('resolution_minutes') : null,
            warningThresholdPercent: $this->has('warning_threshold_percent') ? (int) $this->input('warning_threshold_percent') : null,
        );
    }
}

