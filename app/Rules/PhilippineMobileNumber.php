<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Accepts Philippine mobile numbers as people usually write them
 * (0917 123 4567, 09171234567, +63 917-123-4567, 639171234567).
 */
class PhilippineMobileNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::normalize($value) === null) {
            $fail('Enter a Philippine mobile number, for example 0917 123 4567.');
        }
    }

    /**
     * Convert a Philippine mobile number to the +639XXXXXXXXX form, or null when it is not one.
     */
    public static function normalize(string $value): ?string
    {
        $digits = preg_replace('/[\s\-().]/', '', $value);

        if (preg_match('/^(?:\+?63|0)(9\d{9})$/', $digits, $matches) !== 1) {
            return null;
        }

        return '+63'.$matches[1];
    }
}
