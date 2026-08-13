<?php

namespace App\Domain\Staff\Exceptions;

use RuntimeException;

final class OwnerMinimumRequiredException extends RuntimeException
{
    public function __construct(string $message = 'At least one active owner is required')
    {
        parent::__construct($message, 409);
    }
}
