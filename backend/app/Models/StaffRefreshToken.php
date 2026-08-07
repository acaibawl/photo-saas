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
 * @property string $kindergarten_staff_id
 * @property string $token_hash
 * @property string $family_id
 * @property Carbon $family_expires_at
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read KindergartenStaff $kindergartenStaff
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffRefreshToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffRefreshToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffRefreshToken query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffRefreshToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffRefreshToken whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffRefreshToken whereFamilyExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffRefreshToken whereFamilyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffRefreshToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffRefreshToken whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffRefreshToken whereKindergartenStaffId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffRefreshToken whereRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffRefreshToken whereTokenHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffRefreshToken whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StaffRefreshToken whereUserAgent($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'kindergarten_staff_id', 'token_hash', 'family_id', 'family_expires_at',
    'expires_at', 'revoked_at', 'ip_address', 'user_agent',
])]
#[Hidden(['token_hash'])]
class StaffRefreshToken extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'family_expires_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function kindergartenStaff(): BelongsTo
    {
        return $this->belongsTo(KindergartenStaff::class);
    }
}
