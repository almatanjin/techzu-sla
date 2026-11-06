<?php

namespace App\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreTicketImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin() && (bool) $this->user()?->is_active;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:51200',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    // The content check above judges the bytes; the name must also say CSV or text.
                    $extension = strtolower(pathinfo($value->getClientOriginalName(), PATHINFO_EXTENSION));
                    if (! in_array($extension, ['csv', 'txt'], true)) {
                        $fail('The file must be a .csv or .txt file.');

                        return;
                    }

                    if ($value->getSize() === 0) {
                        $fail('The file cannot be empty.');
                    }
                },
            ],
        ];
    }
}

