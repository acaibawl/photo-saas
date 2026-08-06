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
 * @property string $title
 * @property Carbon $event_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Kindergarten $kindergarten
 * @property-read Collection<int, Photo> $photos
 * @property-read int|null $photos_count
 * @property-read Collection<int, UploadRequest> $uploadRequests
 * @property-read int|null $upload_requests_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereEventDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereKindergartenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['kindergarten_id', 'title', 'event_date'])]
class Album extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
        ];
    }

    public function kindergarten(): BelongsTo
    {
        return $this->belongsTo(Kindergarten::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function uploadRequests(): HasMany
    {
        return $this->hasMany(UploadRequest::class);
    }
}
