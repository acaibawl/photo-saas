<?php

namespace App\Domain\Staff\Exceptions;

use RuntimeException;

final class InvalidStaffCredentialsException extends RuntimeException
{
    public function __construct(string $message = 'Invalid staff credentials')
    {
        parent::__construct($message, 401);
    }
}
