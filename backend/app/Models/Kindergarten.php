<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $stripe_account_id
 * @property Carbon|null $stripe_onboarding_completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Album> $albums
 * @property-read int|null $albums_count
 * @property-read Collection<int, ChildInvitation> $childInvitations
 * @property-read int|null $child_invitations_count
 * @property-read Collection<int, Child> $children
 * @property-read int|null $children_count
 * @property-read Collection<int, GuardianChild> $guardianLinks
 * @property-read int|null $guardian_links_count
 * @property-read Collection<int, Order> $orders
 * @property-read int|null $orders_count
 * @property-read Collection<int, Photo> $photos
 * @property-read int|null $photos_count
 * @property-read Collection<int, StaffInvitation> $staffInvitations
 * @property-read int|null $staff_invitations_count
 * @property-read Collection<int, KindergartenStaff> $staffMembers
 * @property-read int|null $staff_members_count
 * @property-read Collection<int, UploadRequest> $uploadRequests
 * @property-read int|null $upload_requests_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kindergarten newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kindergarten newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kindergarten query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kindergarten whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kindergarten whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kindergarten whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kindergarten whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kindergarten whereStripeAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kindergarten whereStripeOnboardingCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Kindergarten whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'slug', 'stripe_account_id', 'stripe_onboarding_completed_at'])]
class Kindergarten extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'stripe_onboarding_completed_at' => 'datetime',
        ];
    }

    public function staffMembers(): HasMany
    {
        return $this->hasMany(KindergartenStaff::class);
    }

    public function staffInvitations(): HasMany
    {
        return $this->hasMany(StaffInvitation::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(Child::class);
    }

    public function childInvitations(): HasMany
    {
        return $this->hasMany(ChildInvitation::class);
    }

    public function guardianLinks(): HasMany
    {
        return $this->hasMany(GuardianChild::class);
    }

    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function uploadRequests(): HasMany
    {
        return $this->hasMany(UploadRequest::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
