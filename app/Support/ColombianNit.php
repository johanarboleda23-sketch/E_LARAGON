<?php

namespace App\Support;

class ColombianNit
{
    public static function verificationDigit(?string $nit): ?string
    {
        $nit = trim($nit ?? '');
        if ($nit === '' || preg_match('/^[\d\s.,]+$/u', $nit) !== 1) {
            return null;
        }

        $digits = preg_replace('/\D/u', '', $nit);

        if ($digits === '' || strlen($digits) > 15) {
            return null;
        }

        $weights = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];
        $sum = 0;
        $reversedDigits = strrev($digits);

        for ($index = 0, $length = strlen($reversedDigits); $index < $length; $index++) {
            $sum += (int) $reversedDigits[$index] * $weights[$index];
        }

        $remainder = $sum % 11;

        return (string) ($remainder < 2 ? $remainder : 11 - $remainder);
    }
}
