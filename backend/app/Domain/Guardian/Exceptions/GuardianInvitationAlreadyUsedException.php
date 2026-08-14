<?php

namespace App\Domain\Guardian\Exceptions;

use RuntimeException;

final class GuardianInvitationAlreadyUsedException extends RuntimeException
{
    public function __construct(string $message = 'Guardian invitation is already used')
    {
        parent::__construct($message, 409);
    }
}
