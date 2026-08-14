<?php

namespace App\Domain\Guardian\Exceptions;

use RuntimeException;

final class GuardianEmailAlreadyExistsException extends RuntimeException
{
    public function __construct(string $message = 'Guardian email already exists')
    {
        parent::__construct($message, 409);
    }
}
