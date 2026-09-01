<?php

namespace App\Rules;

use App\Support\PhoneNumbers\EgyptianPhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EgyptianMobileNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! EgyptianPhoneNumber::isValid($value)) {
            $fail('The :attribute must be a valid Egyptian mobile number.');
        }
    }
}
