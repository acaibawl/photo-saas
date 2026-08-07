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
 * @property string $guardian_id
 * @property string $kindergarten_id
 * @property string $status
 * @property int $total_amount
 * @property int $platform_fee_amount
 * @property string|null $stripe_payment_intent_id
 * @property string|null $stripe_checkout_session_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Guardian $guardian
 * @property-read Collection<int, OrderItem> $items
 * @property-read int|null $items_count
 * @property-read Kindergarten $kindergarten
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereGuardianId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereKindergartenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePlatformFeeAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStripeCheckoutSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStripePaymentIntentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'guardian_id', 'kindergarten_id', 'status', 'total_amount', 'platform_fee_amount',
    'stripe_payment_intent_id', 'stripe_checkout_session_id',
])]
class Order extends Model
{
    use HasUlids;

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function kindergarten(): BelongsTo
    {
        return $this->belongsTo(Kindergarten::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
