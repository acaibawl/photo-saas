<?php

namespace App\Domain\Staff;

enum StaffRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';
}
