<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $kindergarten_id
 * @property string $child_id
 * @property string $token_hash
 * @property string $label
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property string|null $used_by_guardian_id
 * @property Carbon|null $revoked_at
 * @property string $created_by_staff_id
 * @property string|null $reissued_from_invitation_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Child $child
 * @property-read KindergartenStaff $createdByStaff
 * @property-read Kindergarten $kindergarten
 * @property-read ChildInvitation|null $reissuedFrom
 * @property-read Guardian|null $usedByGuardian
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildInvitation whereChildId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildInvitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildInvitation whereCreatedByStaffId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildInvitation whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildInvitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildInvitation whereKindergartenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildInvitation whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildInvitation whereReissuedFromInvitationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildInvitation whereRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildInvitation whereTokenHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildInvitation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildInvitation whereUsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildInvitation whereUsedByGuardianId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'kindergarten_id', 'child_id', 'token_hash', 'label', 'expires_at', 'used_at',
    'used_by_guardian_id', 'revoked_at', 'created_by_staff_id', 'reissued_from_invitation_id',
])]
#[Hidden(['token_hash'])]
class ChildInvitation extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function kindergarten(): BelongsTo
    {
        return $this->belongsTo(Kindergarten::class);
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function usedByGuardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'used_by_guardian_id');
    }

    public function createdByStaff(): BelongsTo
    {
        return $this->belongsTo(KindergartenStaff::class, 'created_by_staff_id');
    }

    public function reissuedFrom(): BelongsTo
    {
        return $this->belongsTo(ChildInvitation::class, 'reissued_from_invitation_id');
    }
}
