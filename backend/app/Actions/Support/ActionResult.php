<?php

namespace App\Actions\Support;

final class ActionResult
{
    private function __construct(
        public readonly bool $successful,
        public readonly mixed $data = null,
        public readonly ?string $message = null,
    ) {}

    public static function ok(mixed $data = null): self
    {
        return new self(successful: true, data: $data);
    }

    public static function fail(string $message): self
    {
        return new self(successful: false, message: $message);
    }

    public function successful(): bool
    {
        return $this->successful;
    }
}
