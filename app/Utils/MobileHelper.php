<?php

namespace App\Utils;

class MobileHelper
{
    /**
     * Normalise mobile to rightmost 10 digits (strip non-digits, then take last 10).
     * Use for consistent storage; caller should validate length is 10 if required.
     */
    public static function normalize(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $digits = preg_replace('/\D/', '', $value);

        return strlen($digits) >= 10
            ? substr($digits, -10)
            : $digits;
    }
}
