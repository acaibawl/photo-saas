<?php

namespace App\Domain\Shared;

final readonly class SecureToken
{
    private function __construct(
        private string $plainText,
        private string $hash,
    ) {}

    public static function generate(int $bytes = 32): self
    {
        $plainText = bin2hex(random_bytes($bytes));

        return new self($plainText, self::hashOf($plainText));
    }

    public static function hashOf(string $plainText): string
    {
        return hash('sha256', $plainText);
    }

    public function plainText(): string
    {
        return $this->plainText;
    }

    public function hash(): string
    {
        return $this->hash;
    }
}
