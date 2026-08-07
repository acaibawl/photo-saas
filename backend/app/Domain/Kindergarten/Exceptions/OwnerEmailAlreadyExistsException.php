<?php

namespace App\Domain\Kindergarten\Exceptions;

final class OwnerEmailAlreadyExistsException extends \DomainException
{
    public function __construct(string $email)
    {
        parent::__construct("指定されたメールアドレスは既にスタッフとして登録されています: {$email}");
    }
}
