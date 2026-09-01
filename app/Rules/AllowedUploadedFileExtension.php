<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class AllowedUploadedFileExtension implements ValidationRule
{
    /** @param list<string> $extensions */
    public function __construct(private readonly array $extensions) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());

        if ($extension === '' || ! in_array($extension, $this->extensions, true)) {
            $fail('The uploaded file extension is not allowed.');
        }
    }
}
