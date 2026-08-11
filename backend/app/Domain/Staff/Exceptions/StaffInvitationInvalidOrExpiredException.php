<?php

namespace App\Domain\Staff\Exceptions;

use RuntimeException;

final class StaffInvitationInvalidOrExpiredException extends RuntimeException
{
    public function __construct(string $message = 'Staff invitation is invalid or expired')
    {
        parent::__construct($message, 403);
    }
}
