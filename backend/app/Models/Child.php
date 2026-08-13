<?php

namespace App\Models;

use App\Domain\Child\ChildStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $kindergarten_id
 * @property string $name
 * @property ChildStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $child_class_id
 * @property-read ChildClass|null $childClass
 * @property-read Collection<int, GuardianChild> $guardianLinks
 * @property-read int|null $guardian_links_count
 * @property-read Collection<int, ChildInvitation> $invitations
 * @property-read int|null $invitations_count
 * @property-read Kindergarten $kindergarten
 * @property-read Collection<int, PhotoChildTag> $photoTags
 * @property-read int|null $photo_tags_count
 * @property-read PhotoChildTag|null $pivot
 * @property-read Collection<int, Photo> $photos
 * @property-read int|null $photos_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Child newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Child newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Child query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Child whereChildClassId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Child whereClassName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Child whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Child whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Child whereKindergartenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Child whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Child whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Child whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['kindergarten_id', 'child_class_id', 'name', 'status'])]
class Child extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'status' => ChildStatus::class,
        ];
    }

    public function kindergarten(): BelongsTo
    {
        return $this->belongsTo(Kindergarten::class);
    }

    public function childClass(): BelongsTo
    {
        return $this->belongsTo(ChildClass::class, 'child_class_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ChildInvitation::class);
    }

    public function guardianLinks(): HasMany
    {
        return $this->hasMany(GuardianChild::class);
    }

    public function photoTags(): HasMany
    {
        return $this->hasMany(PhotoChildTag::class);
    }

    public function photos(): BelongsToMany
    {
        // using()を指定することでattach()/sync()がPhotoChildTagモデルのsave()を経由し、
        // HasUlidsによる主キー生成が効くようにする(素のpivot insertだとid列がNOT NULL違反になる)
        return $this->belongsToMany(Photo::class, 'photo_child_tags')->using(PhotoChildTag::class);
    }
}
