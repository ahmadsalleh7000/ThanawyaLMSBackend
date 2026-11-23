<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Course scope and staff permissions are checked by the controllers.
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'title_en' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'description_en' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
            'is_published' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
