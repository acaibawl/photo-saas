<?php

namespace App\Domain\Staff\Exceptions;

use RuntimeException;

final class StaffMemberNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'Staff member not found')
    {
        parent::__construct($message, 404);
    }
}
