<?php

namespace App\Domain\Guardian\Exceptions;

use RuntimeException;

final class InvalidGuardianRefreshTokenException extends RuntimeException
{
    public function __construct(string $message = 'Invalid guardian refresh token')
    {
        parent::__construct($message, 401);
    }
}
