<?php

namespace App\Support\PhoneNumbers;

final class EgyptianPhoneNumber
{
    private const E164_PATTERN = '/^\+201[0125][0-9]{8}$/';

    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = strtr(trim((string) $value), [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
        $digits = preg_replace('/\D+/', '', $value);

        if (! $digits) {
            return null;
        }

        if (str_starts_with($digits, '0020')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '01')) {
            $digits = '20'.substr($digits, 1);
        }

        $normalized = '+'.$digits;

        return preg_match(self::E164_PATTERN, $normalized) === 1 ? $normalized : null;
    }

    public static function isValid(mixed $value): bool
    {
        return self::normalize($value) !== null;
    }
}
