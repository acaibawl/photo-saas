<?php

namespace App\Domain\Guardian\Exceptions;

use RuntimeException;

final class GuardianInvitationInvalidOrExpiredException extends RuntimeException
{
    public function __construct(string $message = 'Guardian invitation is invalid or expired')
    {
        parent::__construct($message, 403);
    }
}
