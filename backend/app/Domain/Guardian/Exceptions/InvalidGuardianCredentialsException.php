<?php

namespace App\Domain\Guardian\Exceptions;

use RuntimeException;

final class InvalidGuardianCredentialsException extends RuntimeException
{
    public function __construct(string $message = 'Invalid guardian credentials')
    {
        parent::__construct($message, 401);
    }
}
