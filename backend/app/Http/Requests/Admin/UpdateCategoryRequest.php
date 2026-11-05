<?php

namespace App\Http\Requests\Admin;

use App\Data\UpdateCategoryData;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category instanceof Category && ($this->user()?->can('update', $category) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name') && is_string($this->input('name'))) {
            $this->merge([
                'name' => trim($this->input('name')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->ignore($category),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): UpdateCategoryData
    {
        $hasName = $this->has('name');
        $hasIsActive = $this->has('is_active');

        return new UpdateCategoryData(
            name: $hasName ? (string) $this->validated('name') : null,
            isActive: $hasIsActive ? (bool) $this->validated('is_active') : null,
            hasName: $hasName,
            hasIsActive: $hasIsActive,
        );
    }
}

