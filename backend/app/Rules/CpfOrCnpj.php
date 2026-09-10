<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CpfOrCnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = self::digits($value);

        $valid = match (strlen($digits)) {
            11 => self::isValidCpf($digits),
            14 => self::isValidCnpj($digits),
            default => false,
        };

        if (! $valid) {
            $fail('O campo :attribute deve ser um CPF ou CNPJ válido.');
        }
    }

    public static function digits(mixed $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    public static function isValidCpf(string $digits): bool
    {
        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        foreach ([9, 10] as $position) {
            $sum = 0;

            for ($i = 0; $i < $position; $i++) {
                $sum += (int) $digits[$i] * ($position + 1 - $i);
            }

            $checkDigit = ($sum * 10) % 11 % 10;

            if ($checkDigit !== (int) $digits[$position]) {
                return false;
            }
        }

        return true;
    }

    public static function isValidCnpj(string $digits): bool
    {
        if (strlen($digits) !== 14 || preg_match('/^(\d)\1{13}$/', $digits)) {
            return false;
        }

        foreach ([12 => [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], 13 => [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $position => $weights) {
            $sum = 0;

            foreach ($weights as $i => $weight) {
                $sum += (int) $digits[$i] * $weight;
            }

            $remainder = $sum % 11;
            $checkDigit = $remainder < 2 ? 0 : 11 - $remainder;

            if ($checkDigit !== (int) $digits[$position]) {
                return false;
            }
        }

        return true;
    }
}
