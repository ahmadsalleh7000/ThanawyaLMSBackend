<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['identity' => ['required_without:email', 'string', 'max:255'], 'email' => ['required_without:identity', 'nullable', 'string', 'max:255'], 'password' => ['required', 'string'], 'device_name' => ['nullable', 'string', 'max:120']];
    }
}
