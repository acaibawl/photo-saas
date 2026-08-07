<?php

namespace App\Application\Kindergarten;

use App\Domain\Shared\EmailAddress;

final readonly class CreateKindergartenInput
{
    public function __construct(
        public string $name,
        public string $ownerName,
        public EmailAddress $ownerEmail,
        public bool $sendInvite,
    ) {}
}
