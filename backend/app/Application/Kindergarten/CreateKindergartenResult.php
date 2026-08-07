<?php

namespace App\Application\Kindergarten;

use App\Models\Kindergarten;
use App\Models\KindergartenStaff;
use App\Models\StaffInvitation;

final readonly class CreateKindergartenResult
{
    public function __construct(
        public Kindergarten $kindergarten,
        public KindergartenStaff $owner,
        public StaffInvitation $invitation,
        public string $rawSetupToken,
        public bool $invitationNotificationQueued,
    ) {}
}
