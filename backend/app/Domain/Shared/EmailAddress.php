<?php

namespace App\Domain\Shared;

final readonly class EmailAddress
{
    private function __construct(
        private string $value,
        private string $normalized,
    ) {}

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if (filter_var($trimmed, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException("不正なメールアドレス形式です: {$trimmed}");
        }

        return new self($trimmed, mb_strtolower($trimmed));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function normalized(): string
    {
        return $this->normalized;
    }
}
