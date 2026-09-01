<?php

namespace Tests\Unit;

use App\Rules\EgyptianMobileNumber;
use App\Support\PhoneNumbers\EgyptianPhoneNumber;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class EgyptianPhoneNumberTest extends TestCase
{
    public function test_it_normalizes_supported_egyptian_mobile_formats(): void
    {
        $cases = [
            '01012345678',
            '+20 10 1234 5678',
            '201012345678',
            '00201012345678',
            '٠١٠١٢٣٤٥٦٧٨',
        ];

        foreach ($cases as $phone) {
            $this->assertSame('+201012345678', EgyptianPhoneNumber::normalize($phone));
        }
    }

    public function test_it_rejects_malformed_or_unsupported_numbers(): void
    {
        foreach (['', '12345', '+966501234567', '01312345678', '0101234567', '010123456789'] as $phone) {
            $this->assertNull(EgyptianPhoneNumber::normalize($phone));
        }
    }

    public function test_validation_rule_uses_the_shared_normalizer(): void
    {
        $valid = Validator::make(['phone' => '011 1234 5678'], ['phone' => [new EgyptianMobileNumber]]);
        $invalid = Validator::make(['phone' => '01312345678'], ['phone' => [new EgyptianMobileNumber]]);

        $this->assertFalse($valid->fails());
        $this->assertTrue($invalid->fails());
    }
}
