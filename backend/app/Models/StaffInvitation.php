<?php

namespace App\Models;

use App\Domain\Staff\StaffRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $kindergarten_id
 * @property string $name
 * @property string $email
 * @property string $email_normalized
 * @property StaffRole $role
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 * @property string $created_by_staff_id
 * @property string|null $accepted_staff_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read KindergartenStaff|null $acceptedStaff
 * @property-read KindergartenStaff $createdByStaff
 * @property-read Kindergarten $kindergarten
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffInvitation whereAcceptedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffInvitation whereAcceptedStaffId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffInvitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffInvitation whereCreatedByStaffId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffInvitation whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffInvitation whereEmailNormalized($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffInvitation whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffInvitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffInvitation whereKindergartenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffInvitation whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffInvitation whereRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffInvitation whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffInvitation whereTokenHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffInvitation whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'kindergarten_id', 'name', 'email', 'email_normalized', 'role', 'token_hash',
    'expires_at', 'accepted_at', 'revoked_at', 'created_by_staff_id', 'accepted_staff_id',
])]
#[Hidden(['token_hash'])]
class StaffInvitation extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'role' => StaffRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function kindergarten(): BelongsTo
    {
        return $this->belongsTo(Kindergarten::class);
    }

    public function createdByStaff(): BelongsTo
    {
        return $this->belongsTo(KindergartenStaff::class, 'created_by_staff_id');
    }

    public function acceptedStaff(): BelongsTo
    {
        return $this->belongsTo(KindergartenStaff::class, 'accepted_staff_id');
    }
}
