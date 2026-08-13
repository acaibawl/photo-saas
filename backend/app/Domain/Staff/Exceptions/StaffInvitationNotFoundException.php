<?php

namespace App\Domain\Staff\Exceptions;

use RuntimeException;

final class StaffInvitationNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'Staff invitation not found')
    {
        parent::__construct($message, 404);
    }
}
