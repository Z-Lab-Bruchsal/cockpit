<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A phone number we can call back: digits with the usual separators (+, spaces, slashes, dashes, brackets, dots)
 * and at least six digits.
 */
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = trim((string) $value);

        if (! preg_match('/^\+?[0-9\s\/().-]+$/', $value) || preg_match_all('/[0-9]/', $value) < 6) {
            $fail('Das ist keine gültige Telefonnummer.');
        }
    }
}
