<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $guardian_id
 * @property string $child_id
 * @property string $kindergarten_id
 * @property string $label
 * @property Carbon $linked_at
 * @property Carbon|null $unlinked_at
 * @property string|null $unlinked_by_staff_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Child $child
 * @property-read Guardian $guardian
 * @property-read Kindergarten $kindergarten
 * @property-read KindergartenStaff|null $unlinkedByStaff
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianChild newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianChild newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianChild query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianChild whereChildId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianChild whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianChild whereGuardianId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianChild whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianChild whereKindergartenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianChild whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianChild whereLinkedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianChild whereUnlinkedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianChild whereUnlinkedByStaffId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuardianChild whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Table(name: 'guardian_child')]
#[Fillable([
    'guardian_id', 'child_id', 'kindergarten_id', 'label', 'linked_at',
    'unlinked_at', 'unlinked_by_staff_id',
])]
class GuardianChild extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
            'unlinked_at' => 'datetime',
        ];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function kindergarten(): BelongsTo
    {
        return $this->belongsTo(Kindergarten::class);
    }

    public function unlinkedByStaff(): BelongsTo
    {
        return $this->belongsTo(KindergartenStaff::class, 'unlinked_by_staff_id');
    }
}
