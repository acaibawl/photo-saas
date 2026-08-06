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
 * @property string $guardian_id
 * @property string $token_hash
 * @property string $family_id
 * @property Carbon $family_expires_at
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Guardian $guardian
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianRefreshToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianRefreshToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianRefreshToken query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianRefreshToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianRefreshToken whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianRefreshToken whereFamilyExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianRefreshToken whereFamilyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianRefreshToken whereGuardianId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianRefreshToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianRefreshToken whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianRefreshToken whereRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianRefreshToken whereTokenHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianRefreshToken whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianRefreshToken whereUserAgent($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'guardian_id', 'token_hash', 'family_id', 'family_expires_at',
    'expires_at', 'revoked_at', 'ip_address', 'user_agent',
])]
#[Hidden(['token_hash'])]
class GuardianRefreshToken extends Model
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

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }
}
