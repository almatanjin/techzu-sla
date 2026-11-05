<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListCategoriesRequest extends FormRequest
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
            'include_inactive' => ['sometimes', 'boolean'],
        ];
    }

    public function includeInactive(): bool
    {
        return $this->boolean('include_inactive');
    }
}

