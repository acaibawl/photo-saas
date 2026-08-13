<?php

namespace App\Models;

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
 * @property string|null $album_id
 * @property string|null $storage_path
 * @property string|null $preview_path
 * @property int|null $price
 * @property string $file_key
 * @property string $preview_status
 * @property string $uploaded_by_staff_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Album|null $album
 * @property-read Collection<int, PhotoChildTag> $childTags
 * @property-read int|null $child_tags_count
 * @property-read Collection<int, Entitlement> $entitlements
 * @property-read int|null $entitlements_count
 * @property-read Kindergarten $kindergarten
 * @property-read Collection<int, OrderItem> $orderItems
 * @property-read int|null $order_items_count
 * @property-read PhotoChildTag|null $pivot
 * @property-read Collection<int, Child> $taggedChildren
 * @property-read int|null $tagged_children_count
 * @property-read Collection<int, UploadJob> $uploadJobs
 * @property-read int|null $upload_jobs_count
 * @property-read KindergartenStaff $uploadedByStaff
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Photo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Photo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Photo query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Photo whereAlbumId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Photo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Photo whereFileKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Photo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Photo whereKindergartenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Photo wherePreviewPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Photo wherePreviewStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Photo wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Photo whereStoragePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Photo whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Photo whereUploadedByStaffId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'kindergarten_id', 'album_id', 'storage_path', 'preview_path', 'price',
    'file_key', 'preview_status', 'uploaded_by_staff_id',
])]
class Photo extends Model
{
    use HasUlids;

    public function kindergarten(): BelongsTo
    {
        return $this->belongsTo(Kindergarten::class);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function uploadedByStaff(): BelongsTo
    {
        return $this->belongsTo(KindergartenStaff::class, 'uploaded_by_staff_id');
    }

    public function childTags(): HasMany
    {
        return $this->hasMany(PhotoChildTag::class);
    }

    public function taggedChildren(): BelongsToMany
    {
        // using()を指定することでattach()/sync()がPhotoChildTagモデルのsave()を経由し、
        // HasUlidsによる主キー生成が効くようにする(素のpivot insertだとid列がNOT NULL違反になる)
        return $this->belongsToMany(Child::class, 'photo_child_tags')->using(PhotoChildTag::class);
    }

    public function uploadJobs(): HasMany
    {
        return $this->hasMany(UploadJob::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }
}
