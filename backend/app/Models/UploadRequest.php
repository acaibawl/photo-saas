<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $kindergarten_id
 * @property string|null $album_id
 * @property int|null $price
 * @property array<array-key, mixed>|null $child_ids
 * @property string $status
 * @property int $total_files
 * @property int $accepted_count
 * @property string $requested_by_staff_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Album|null $album
 * @property-read Collection<int, UploadJob> $jobs
 * @property-read int|null $jobs_count
 * @property-read Kindergarten $kindergarten
 * @property-read KindergartenStaff $requestedByStaff
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadRequest query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadRequest whereAcceptedCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadRequest whereAlbumId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadRequest whereChildIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadRequest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadRequest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadRequest whereKindergartenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadRequest wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadRequest whereRequestedByStaffId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadRequest whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadRequest whereTotalFiles($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadRequest whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'kindergarten_id', 'album_id', 'price', 'child_ids', 'status',
    'total_files', 'accepted_count', 'requested_by_staff_id',
])]
class UploadRequest extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'child_ids' => 'array',
        ];
    }

    public function kindergarten(): BelongsTo
    {
        return $this->belongsTo(Kindergarten::class);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function requestedByStaff(): BelongsTo
    {
        return $this->belongsTo(KindergartenStaff::class, 'requested_by_staff_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(UploadJob::class);
    }
}
