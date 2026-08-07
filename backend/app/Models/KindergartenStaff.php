<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * @property string $id
 * @property string $kindergarten_id
 * @property string $name
 * @property string $email
 * @property string $email_normalized
 * @property string $password_hash
 * @property string $role
 * @property Carbon|null $last_login_at
 * @property Carbon|null $invited_at
 * @property Carbon|null $joined_at
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StaffInvitation|null $acceptedInvitation
 * @property-read Collection<int, ChildInvitation> $createdChildInvitations
 * @property-read int|null $created_child_invitations_count
 * @property-read Collection<int, StaffInvitation> $createdStaffInvitations
 * @property-read int|null $created_staff_invitations_count
 * @property-read Kindergarten $kindergarten
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, StaffRefreshToken> $refreshTokens
 * @property-read int|null $refresh_tokens_count
 * @property-read Collection<int, GuardianChild> $unlinkedGuardianLinks
 * @property-read int|null $unlinked_guardian_links_count
 * @property-read Collection<int, UploadRequest> $uploadRequests
 * @property-read int|null $upload_requests_count
 * @property-read Collection<int, Photo> $uploadedPhotos
 * @property-read int|null $uploaded_photos_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KindergartenStaff newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KindergartenStaff newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KindergartenStaff query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KindergartenStaff whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KindergartenStaff whereDeactivatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KindergartenStaff whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KindergartenStaff whereEmailNormalized($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KindergartenStaff whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KindergartenStaff whereInvitedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KindergartenStaff whereJoinedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KindergartenStaff whereKindergartenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KindergartenStaff whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KindergartenStaff whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KindergartenStaff wherePasswordHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KindergartenStaff whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KindergartenStaff whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Table(name: 'kindergarten_staff')]
#[Fillable([
    'kindergarten_id', 'name', 'email', 'email_normalized', 'password_hash',
    'role', 'last_login_at', 'invited_at', 'joined_at', 'deactivated_at',
])]
#[Hidden(['password_hash'])]
class KindergartenStaff extends Authenticatable implements JWTSubject
{
    use HasUlids, Notifiable;

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'invited_at' => 'datetime',
            'joined_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'kindergarten_id' => $this->kindergarten_id,
            'role' => $this->role,
        ];
    }

    public function kindergarten(): BelongsTo
    {
        return $this->belongsTo(Kindergarten::class);
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(StaffRefreshToken::class);
    }

    public function createdStaffInvitations(): HasMany
    {
        return $this->hasMany(StaffInvitation::class, 'created_by_staff_id');
    }

    public function acceptedInvitation(): HasOne
    {
        return $this->hasOne(StaffInvitation::class, 'accepted_staff_id');
    }

    public function createdChildInvitations(): HasMany
    {
        return $this->hasMany(ChildInvitation::class, 'created_by_staff_id');
    }

    public function unlinkedGuardianLinks(): HasMany
    {
        return $this->hasMany(GuardianChild::class, 'unlinked_by_staff_id');
    }

    public function uploadedPhotos(): HasMany
    {
        return $this->hasMany(Photo::class, 'uploaded_by_staff_id');
    }

    public function uploadRequests(): HasMany
    {
        return $this->hasMany(UploadRequest::class, 'requested_by_staff_id');
    }
}
