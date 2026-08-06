<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $order_item_id
 * @property string $guardian_id
 * @property string $photo_id
 * @property Carbon $granted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Guardian $guardian
 * @property-read OrderItem $orderItem
 * @property-read Photo $photo
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Entitlement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Entitlement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Entitlement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Entitlement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Entitlement whereGrantedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Entitlement whereGuardianId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Entitlement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Entitlement whereOrderItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Entitlement wherePhotoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Entitlement whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['order_item_id', 'guardian_id', 'photo_id', 'granted_at'])]
class Entitlement extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }
}
