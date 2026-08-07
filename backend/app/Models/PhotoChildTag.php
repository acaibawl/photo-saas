<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $photo_id
 * @property string $child_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Child $child
 * @property-read Photo $photo
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhotoChildTag newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhotoChildTag newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhotoChildTag query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhotoChildTag whereChildId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhotoChildTag whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhotoChildTag whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhotoChildTag wherePhotoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PhotoChildTag whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Table(name: 'photo_child_tags')]
#[Fillable(['photo_id', 'child_id'])]
class PhotoChildTag extends Pivot
{
    use HasUlids;

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }
}
