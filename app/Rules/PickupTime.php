<?php

namespace App\Rules;

use App\Support\PickupHours;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PickupTime implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! PickupHours::contains($value)) {
            $fail(PickupHours::message());
        }
    }
}
