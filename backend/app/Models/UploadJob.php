<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $upload_request_id
 * @property string $file_key
 * @property string $original_filename
 * @property string $status
 * @property string|null $photo_id
 * @property string|null $error_message
 * @property int $attempts
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Photo|null $photo
 * @property-read UploadRequest $uploadRequest
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadJob newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadJob newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadJob query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadJob whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadJob whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadJob whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadJob whereFileKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadJob whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadJob whereOriginalFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadJob wherePhotoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadJob whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadJob whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadJob whereUploadRequestId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'upload_request_id', 'file_key', 'original_filename', 'status',
    'photo_id', 'error_message', 'attempts',
])]
class UploadJob extends Model
{
    use HasUlids;

    public function uploadRequest(): BelongsTo
    {
        return $this->belongsTo(UploadRequest::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }
}
