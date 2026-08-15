<?php

namespace App\Application\Staff\Photo;

use App\Domain\Album\Exceptions\AlbumNotFoundException;
use App\Domain\Child\Exceptions\ChildTenantScopeViolationException;
use App\Domain\Photo\Exceptions\PhotoNotFoundException;
use App\Domain\Photo\Exceptions\PhotoNotReadyForUpdateException;
use App\Jobs\ProcessUploadBatchJob;
use App\Models\Album;
use App\Models\Child;
use App\Models\KindergartenStaff;
use App\Models\Photo;
use App\Models\UploadJob;
use App\Models\UploadRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class PhotoManagementService
{
    public function acceptUploadBatch(
        KindergartenStaff $actor,
        ?string $albumId,
        array $files,
        ?int $price,
        array $childIds,
    ): array {
        $album = $albumId !== null ? $this->findAlbumForActor($actor, $albumId) : null;
        $validatedChildIds = $this->validateChildIdsWithinKindergarten($actor, $childIds);

        $uploadRequest = DB::transaction(function () use ($actor, $album, $files, $price, $validatedChildIds): UploadRequest {
            $uploadRequest = UploadRequest::create([
                'kindergarten_id' => $actor->kindergarten_id,
                'album_id' => $album?->id,
                'price' => $price,
                'child_ids' => $validatedChildIds,
                'status' => 'accepted',
                'total_files' => count($files),
                'accepted_count' => 0,
                'requested_by_staff_id' => $actor->id,
            ]);

            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $contentHash = hash_file('sha256', $file->getRealPath());
                $fileKey = hash('sha256', implode(':', [
                    $actor->kindergarten_id,
                    $uploadRequest->id,
                    $contentHash,
                ]));

                $job = UploadJob::query()->firstOrCreate(
                    ['file_key' => $fileKey],
                    [
                        'upload_request_id' => $uploadRequest->id,
                        'original_filename' => $file->getClientOriginalName(),
                        'status' => 'accepted',
                        'attempts' => 0,
                    ],
                );

                if ($job->wasRecentlyCreated) {
                    $job->refresh();
                }
            }

            return $uploadRequest;
        });

        $acceptedFiles = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $contentHash = hash_file('sha256', $file->getRealPath());
            $fileKey = hash('sha256', implode(':', [
                $actor->kindergarten_id,
                $uploadRequest->id,
                $contentHash,
            ]));

            $job = UploadJob::query()->where('upload_request_id', $uploadRequest->id)->where('file_key', $fileKey)->first();

            if ($job === null || $job->status !== 'accepted') {
                continue;
            }

            $extension = $file->extension() !== '' ? $file->extension() : $file->clientExtension();
            $filename = $extension !== '' ? $fileKey.'.'.$extension : $fileKey;

            $uploaded = Storage::disk('s3')->putFileAs(
                'uploads/tmp/'.$actor->kindergarten_id.'/'.$uploadRequest->id,
                $file,
                $filename,
            );

            if ($uploaded === false) {
                $job->forceFill([
                    'status' => 'failed',
                    'error_message' => 'Failed to store uploaded file in S3.',
                ])->save();

                $uploadRequest->refresh();
                $failedJobsCount = $uploadRequest->jobs()->where('status', 'failed')->count();
                $uploadRequest->forceFill([
                    'status' => $failedJobsCount > 0 ? 'failed' : 'accepted',
                    'accepted_count' => max(0, $uploadRequest->accepted_count),
                ])->save();

                return [
                    'batch_id' => $uploadRequest->id,
                    'status' => $uploadRequest->status,
                    'accepted_count' => $uploadRequest->accepted_count,
                    'total_files' => $uploadRequest->total_files,
                    'queued_at' => $uploadRequest->created_at?->toIso8601String(),
                ];
            }

            $acceptedFiles[] = $job;
        }

        $acceptedCount = count($acceptedFiles);
        $uploadRequest->forceFill(['accepted_count' => $acceptedCount])->save();

        if ($acceptedCount === 0) {
            $uploadRequest->forceFill(['status' => 'failed'])->save();

            return [
                'batch_id' => $uploadRequest->id,
                'status' => $uploadRequest->status,
                'accepted_count' => 0,
                'total_files' => $uploadRequest->total_files,
                'queued_at' => $uploadRequest->created_at?->toIso8601String(),
            ];
        }

        DB::afterCommit(function () use ($uploadRequest): void {
            ProcessUploadBatchJob::dispatch($uploadRequest->id);
        });

        return [
            'batch_id' => $uploadRequest->id,
            'status' => $uploadRequest->status,
            'accepted_count' => $acceptedCount,
            'total_files' => $uploadRequest->total_files,
            'queued_at' => $uploadRequest->created_at?->toIso8601String(),
        ];
    }

    public function getUploadBatchStatus(KindergartenStaff $actor, string $uploadRequestId): array
    {
        $uploadRequest = UploadRequest::query()
            ->with('jobs')
            ->where('id', $uploadRequestId)
            ->where('kindergarten_id', $actor->kindergarten_id)
            ->first();

        if ($uploadRequest === null) {
            throw new PhotoNotFoundException;
        }

        return [
            'batch_id' => $uploadRequest->id,
            'status' => $uploadRequest->status,
            'accepted_count' => $uploadRequest->accepted_count,
            'total_files' => $uploadRequest->total_files,
            'queued_at' => $uploadRequest->created_at?->toIso8601String(),
            'jobs' => $uploadRequest->jobs
                ->map(fn (UploadJob $job): array => [
                    'job_id' => $job->id,
                    'file_key' => $job->file_key,
                    'original_filename' => $job->original_filename,
                    'status' => $job->status,
                    'photo_id' => $job->photo_id,
                    'attempts' => $job->attempts,
                    'error_message' => $job->error_message,
                ])
                ->values()
                ->all(),
        ];
    }

    public function listPhotos(
        KindergartenStaff $actor,
        ?string $albumId,
        ?string $childId,
        ?string $keyword,
        ?string $priceStatus,
        ?int $priceMin,
        ?int $priceMax,
        ?string $previewStatus,
        ?string $createdFrom,
        ?string $createdTo,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        if ($albumId !== null) {
            $this->findAlbumForActor($actor, $albumId);
        }

        if ($childId !== null) {
            $this->validateChildIdsWithinKindergarten($actor, [$childId]);
        }

        $query = Photo::query()
            ->with(['album', 'taggedChildren.childClass'])
            ->where('kindergarten_id', $actor->kindergarten_id)
            ->orderByDesc('created_at');

        if ($albumId !== null) {
            $query->where('album_id', $albumId);
        }

        if ($childId !== null) {
            $query->whereHas('taggedChildren', function ($builder) use ($childId): void {
                $builder->where('children.id', $childId);
            });
        }

        if ($keyword !== null && trim($keyword) !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($keyword));

            $query->where(function ($builder) use ($escaped): void {
                $builder->whereRaw('photos.file_key LIKE ? ESCAPE ?', ["%{$escaped}%", '\\'])
                    ->orWhereHas('album', function ($albumQuery) use ($escaped): void {
                        $albumQuery->whereRaw('title LIKE ? ESCAPE ?', ["%{$escaped}%", '\\']);
                    })
                    ->orWhereHas('taggedChildren', function ($childQuery) use ($escaped): void {
                        $childQuery->whereRaw('children.name LIKE ? ESCAPE ?', ["%{$escaped}%", '\\']);
                    });
            });
        }

        if ($priceStatus === 'set') {
            $query->whereNotNull('price');
        }

        if ($priceStatus === 'unset') {
            $query->whereNull('price');
        }

        if ($priceMin !== null) {
            $query->where('price', '>=', $priceMin);
        }

        if ($priceMax !== null) {
            $query->where('price', '<=', $priceMax);
        }

        if ($previewStatus !== null) {
            $query->where('preview_status', $previewStatus);
        }

        if ($createdFrom !== null) {
            $query->whereDate('created_at', '>=', $createdFrom);
        }

        if ($createdTo !== null) {
            $query->whereDate('created_at', '<=', $createdTo);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findPhotoDetail(KindergartenStaff $actor, string $photoId): array
    {
        $photo = $this->findPhotoForActor($actor, $photoId);
        $photo->loadMissing(['album', 'taggedChildren.childClass']);

        return [
            'photo_id' => $photo->id,
            'album_id' => $photo->album_id,
            'album_title' => $photo->album?->title,
            'price' => $photo->price,
            'is_sellable' => $this->isSellable($photo),
            'preview_status' => $photo->preview_status,
            'preview_url' => $this->fileUrl('s3', $photo->preview_path),
            'original_url' => $this->fileUrl('s3', $photo->storage_path),
            'tagged_children' => $photo->taggedChildren
                ->map(fn (Child $child): array => [
                    'child_id' => $child->id,
                    'name' => $child->name,
                    'class_name' => $child->childClass->name,
                ])
                ->values()
                ->all(),
            'created_at' => $photo->created_at?->toIso8601String(),
            'updated_at' => $photo->updated_at?->toIso8601String(),
        ];
    }

    public function updatePhoto(
        KindergartenStaff $actor,
        string $photoId,
        bool $albumIdProvided,
        ?string $albumId,
        bool $priceProvided,
        ?int $price,
        bool $childIdsProvided,
        array $childIds,
    ): array {
        return DB::transaction(function () use (
            $actor,
            $photoId,
            $albumIdProvided,
            $albumId,
            $priceProvided,
            $price,
            $childIdsProvided,
            $childIds,
        ): array {
            $photo = Photo::query()
                ->with('taggedChildren')
                ->where('id', $photoId)
                ->where('kindergarten_id', $actor->kindergarten_id)
                ->lockForUpdate()
                ->first();

            if ($photo === null) {
                throw new PhotoNotFoundException;
            }

            if ($photo->preview_status !== 'ready') {
                throw new PhotoNotReadyForUpdateException;
            }

            if ($albumIdProvided) {
                $photo->album_id = $albumId !== null ? $this->findAlbumForActor($actor, $albumId)->id : null;
            }

            if ($priceProvided) {
                $photo->price = $price;
            }

            if ($childIdsProvided) {
                $validatedChildIds = $this->validateChildIdsWithinKindergarten($actor, $childIds);
                $photo->save();
                $photo->taggedChildren()->sync($validatedChildIds);
                $photo->load('taggedChildren');
            } else {
                $photo->save();
            }

            $taggedChildIds = $photo->taggedChildren
                ->pluck('id')
                ->values()
                ->all();

            return [
                'photo_id' => $photo->id,
                'album_id' => $photo->album_id,
                'price' => $photo->price,
                'child_ids' => $taggedChildIds,
                'is_sellable' => $this->isSellable($photo),
                'updated_at' => $photo->fresh()?->updated_at?->toIso8601String(),
            ];
        });
    }

    public function formatPhotoSummary(Photo $photo): array
    {
        $photo->loadMissing('taggedChildren');

        return [
            'photo_id' => $photo->id,
            'album_id' => $photo->album_id,
            'price' => $photo->price,
            'is_sellable' => $this->isSellable($photo),
            'preview_status' => $photo->preview_status,
            'preview_url' => $this->fileUrl('s3', $photo->preview_path),
            'created_at' => $photo->created_at?->toIso8601String(),
            'tagged_child_ids' => $photo->taggedChildren->pluck('id')->values()->all(),
        ];
    }

    private function findAlbumForActor(KindergartenStaff $actor, string $albumId): Album
    {
        $album = Album::query()
            ->where('id', $albumId)
            ->where('kindergarten_id', $actor->kindergarten_id)
            ->first();

        if ($album === null) {
            throw new AlbumNotFoundException;
        }

        return $album;
    }

    private function findPhotoForActor(KindergartenStaff $actor, string $photoId): Photo
    {
        $photo = Photo::query()
            ->where('id', $photoId)
            ->where('kindergarten_id', $actor->kindergarten_id)
            ->first();

        if ($photo === null) {
            throw new PhotoNotFoundException;
        }

        return $photo;
    }

    private function validateChildIdsWithinKindergarten(KindergartenStaff $actor, array $childIds): array
    {
        if ($childIds === []) {
            return [];
        }

        $children = Child::query()
            ->whereIn('id', $childIds)
            ->whereHas('childClass', function ($builder) use ($actor): void {
                $builder->where('kindergarten_id', $actor->kindergarten_id);
            })
            ->pluck('id');

        $validatedChildIds = $children->values()->all();

        sort($validatedChildIds);

        $expectedChildIds = array_values(array_unique($childIds));
        sort($expectedChildIds);

        if ($validatedChildIds !== $expectedChildIds) {
            throw new ChildTenantScopeViolationException('One or more child_ids do not belong to the authenticated kindergarten.');
        }

        return $validatedChildIds;
    }

    private function isSellable(Photo $photo): bool
    {
        $taggedChildren = $photo->relationLoaded('taggedChildren')
            ? $photo->taggedChildren
            : $photo->taggedChildren()->get();

        return $photo->preview_status === 'ready'
            && $photo->price !== null
            && $taggedChildren->isNotEmpty();
    }

    private function fileUrl(string $disk, ?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        /** @var FilesystemAdapter $filesystem */
        $filesystem = Storage::disk($disk);

        return $filesystem->temporaryUrl($path, now()->addMinutes(10));
    }
}
