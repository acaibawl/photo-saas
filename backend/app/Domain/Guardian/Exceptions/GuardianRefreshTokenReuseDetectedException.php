<?php

namespace App\Domain\Guardian\Exceptions;

use RuntimeException;

final class GuardianRefreshTokenReuseDetectedException extends RuntimeException
{
    public function __construct(string $message = 'Guardian refresh token reuse detected')
    {
        parent::__construct($message, 401);
    }
}
