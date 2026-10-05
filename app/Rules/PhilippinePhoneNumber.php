<?php

namespace App\Rules;

use App\Support\PhilippineContact;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PhilippinePhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || PhilippineContact::normalize($value) === null) {
            $fail('Enter a valid Philippine mobile or landline number. Include the area code for a landline.');
        }
    }
}
