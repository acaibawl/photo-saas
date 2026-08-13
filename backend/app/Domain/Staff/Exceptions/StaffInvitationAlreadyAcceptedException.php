<?php

namespace App\Domain\Staff\Exceptions;

use RuntimeException;

final class StaffInvitationAlreadyAcceptedException extends RuntimeException
{
    public function __construct(string $message = 'Staff invitation already accepted')
    {
        parent::__construct($message, 409);
    }
}
