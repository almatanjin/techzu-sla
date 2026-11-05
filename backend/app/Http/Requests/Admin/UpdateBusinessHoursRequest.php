<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateBusinessHoursRequest extends FormRequest
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
            'days' => ['required', 'array', 'size:7'],
            'days.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'days.*.is_working_day' => ['required', 'boolean'],
            'days.*.opens_at' => ['nullable', 'date_format:H:i'],
            'days.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $days = $this->input('days');
                if (! is_array($days)) {
                    return;
                }

                $hasWorkingDay = false;

                foreach ($days as $index => $day) {
                    $isWorking = (bool) ($day['is_working_day'] ?? false);
                    if ($isWorking) {
                        $hasWorkingDay = true;
                        $opens = $day['opens_at'] ?? null;
                        $closes = $day['closes_at'] ?? null;

                        if (! $opens) {
                            $validator->errors()->add("days.{$index}.opens_at", 'Opening time is required for working days.');
                        }
                        if (! $closes) {
                            $validator->errors()->add("days.{$index}.closes_at", 'Closing time is required for working days.');
                        }

                        if ($opens && $closes && $closes <= $opens) {
                            $validator->errors()->add("days.{$index}.closes_at", 'Closing time must be after opening time.');
                        }
                    }
                }

                if (! $hasWorkingDay) {
                    $validator->errors()->add('days', 'At least one working day is required.');
                }
            },
        ];
    }
}

