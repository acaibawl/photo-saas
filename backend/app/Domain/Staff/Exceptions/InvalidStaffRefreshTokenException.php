<?php

namespace App\Domain\Staff\Exceptions;

use RuntimeException;

final class InvalidStaffRefreshTokenException extends RuntimeException
{
    public function __construct(string $message = 'Invalid staff refresh token')
    {
        parent::__construct($message, 401);
    }
}
