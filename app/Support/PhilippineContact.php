<?php

namespace App\Support;

use App\Rules\PhilippinePhoneNumber;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

final class PhilippineContact
{
    public static function rules(): array
    {
        return ['required', 'string', 'max:40', new PhilippinePhoneNumber];
    }

    public static function normalize(?string $input): ?string
    {
        $input = trim((string) $input);
        // Do not allow vanity letters to be converted into an unrelated number.
        if (! preg_match('/^\+?[0-9\s().-]+$/D', $input)) {
            return null;
        }
        try {
            $utility = PhoneNumberUtil::getInstance();
            $number = $utility->parse($input, 'PH');
            if (! $utility->isValidNumberForRegion($number, 'PH') || $number->hasExtension()
                || ! in_array($utility->getNumberType($number), [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE, PhoneNumberType::FIXED_LINE_OR_MOBILE], true)) {
                return null;
            }

            // Preserve the application's domestic-digit storage convention.
            return '0'.$number->getNationalNumber();
        } catch (NumberParseException) {
            return null;
        }
    }
}
