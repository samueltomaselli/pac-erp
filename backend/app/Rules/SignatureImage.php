<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SignatureImage implements ValidationRule
{
    public const PREFIX = 'data:image/png;base64,';

    public const MAX_BYTES = 512 * 1024;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $binary = self::decode($value);

        if ($binary === null) {
            $fail('A assinatura enviada é inválida. Assine novamente.');

            return;
        }

        if (strlen($binary) > self::MAX_BYTES) {
            $fail('A assinatura enviada é grande demais. Assine novamente.');
        }
    }

    public static function decode(mixed $value): ?string
    {
        if (! is_string($value) || ! str_starts_with($value, self::PREFIX)) {
            return null;
        }

        $binary = base64_decode(substr($value, strlen(self::PREFIX)), true);

        if ($binary === false || ! str_starts_with($binary, "\x89PNG\r\n\x1a\n")) {
            return null;
        }

        return $binary;
    }
}
