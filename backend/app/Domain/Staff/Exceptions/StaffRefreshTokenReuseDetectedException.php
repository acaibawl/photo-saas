<?php

namespace App\Domain\Staff\Exceptions;

use RuntimeException;

final class StaffRefreshTokenReuseDetectedException extends RuntimeException
{
    public function __construct(string $message = 'Refresh token reuse detected')
    {
        parent::__construct($message, 401);
    }
}
