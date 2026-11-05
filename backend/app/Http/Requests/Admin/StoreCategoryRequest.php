<?php

namespace App\Http\Requests\Admin;

use App\Data\CreateCategoryData;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Category::class) ?? false;
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
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name'),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): CreateCategoryData
    {
        return new CreateCategoryData(
            name: (string) $this->validated('name'),
            isActive: (bool) ($this->validated('is_active') ?? true),
        );
    }
}

