<?php

namespace App\Domain\Staff\Exceptions;

use RuntimeException;

final class StaffRoleChangeSelfForbiddenException extends RuntimeException
{
    public function __construct(string $message = 'Changing your own role is forbidden')
    {
        parent::__construct($message, 409);
    }
}
