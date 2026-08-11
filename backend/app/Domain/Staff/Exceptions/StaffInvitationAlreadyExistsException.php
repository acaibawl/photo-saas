<?php

namespace App\Domain\Staff\Exceptions;

use RuntimeException;

final class StaffInvitationAlreadyExistsException extends RuntimeException
{
    public function __construct(string $message = 'Staff invitation already exists')
    {
        parent::__construct($message, 409);
    }
}
