<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SetForcedPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'parent' && $this->user()->must_change_password;
    }

    public function rules(): array
    {
        return ['password' => ['required', 'confirmed', Password::min(8)]];
    }
}
