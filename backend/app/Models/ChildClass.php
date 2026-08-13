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
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Child> $children
 * @property-read int|null $children_count
 * @property-read Kindergarten $kindergarten
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildClass newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildClass newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildClass query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildClass whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildClass whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildClass whereKindergartenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildClass whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChildClass whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['kindergarten_id', 'name'])]
class ChildClass extends Model
{
    use HasUlids;

    protected $table = 'child_classes';

    public function kindergarten(): BelongsTo
    {
        return $this->belongsTo(Kindergarten::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(Child::class, 'child_class_id');
    }
}
