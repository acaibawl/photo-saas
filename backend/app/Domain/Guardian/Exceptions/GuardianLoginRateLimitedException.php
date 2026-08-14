<?php

namespace App\Domain\Guardian\Exceptions;

use RuntimeException;

final class GuardianLoginRateLimitedException extends RuntimeException
{
    public function __construct(string $message = 'Too many login attempts')
    {
        parent::__construct($message, 429);
    }
}
