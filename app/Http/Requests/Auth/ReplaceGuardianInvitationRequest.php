<?php

namespace App\Http\Requests\Auth;

use App\Enums\GuardianRelationshipType;
use App\Rules\EgyptianMobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReplaceGuardianInvitationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'parent_phone' => ['required', new EgyptianMobileNumber],
            'relationship_type' => ['sometimes', Rule::enum(GuardianRelationshipType::class)],
            'relationship_details' => ['nullable', 'required_if:relationship_type,other', 'prohibited_unless:relationship_type,other', 'string', 'min:2', 'max:80'],
        ];
    }
}
