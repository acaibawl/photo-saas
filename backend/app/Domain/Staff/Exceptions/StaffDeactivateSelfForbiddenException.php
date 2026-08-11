<?php

namespace App\Domain\Staff\Exceptions;

use RuntimeException;

final class StaffDeactivateSelfForbiddenException extends RuntimeException
{
    public function __construct(string $message = 'Deactivating yourself is forbidden')
    {
        parent::__construct($message, 409);
    }
}
