<?php

namespace App\Domain\Kindergarten\Exceptions;

final class OwnerEmailAlreadyExistsException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('指定されたメールアドレスは既にスタッフとして登録されています。');
    }
}
