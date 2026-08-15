<?php

namespace App\Application\Guardian\Photo;

use App\Domain\Photo\Exceptions\PhotoAccessDeniedException;
use App\Domain\Photo\Exceptions\PhotoNotFoundException;
use App\Models\Album;
use App\Models\Guardian;
use App\Models\GuardianChild;
use App\Models\Photo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

final class GuardianPhotoService
{
    public function listAlbums(Guardian $guardian, ?string $childId): array
    {
        if ($childId !== null && trim($childId) !== '') {
            $this->assertActiveChildAccess($guardian, $childId);
        }

        $query = Album::query()
            ->select('albums.*')
            ->join('photos', 'photos.album_id', '=', 'albums.id')
            ->join('photo_child_tags', 'photo_child_tags.photo_id', '=', 'photos.id')
            ->join('guardian_child', function ($join) use ($guardian): void {
                $join->on('guardian_child.child_id', '=', 'photo_child_tags.child_id')
                    ->where('guardian_child.guardian_id', $guardian->id)
                    ->whereNull('guardian_child.unlinked_at');
            })
            ->whereNotNull('photos.album_id')
            ->whereNotNull('photos.preview_path')
            ->where('photos.preview_status', 'ready')
            ->distinct();

        if ($childId !== null && trim($childId) !== '') {
            $query->where('guardian_child.child_id', $childId);
        }

        $albums = $query
            ->orderByDesc('albums.event_date')
            ->orderByDesc('albums.created_at')
            ->get();

        return [
            'data' => $albums->map(fn (Album $album): array => [
                'album_id' => $album->id,
                'title' => $album->title,
                'event_date' => $album->event_date->toDateString(),
            ])->values()->all(),
        ];
    }

    public function listPhotos(
        Guardian $guardian,
        ?string $childId,
        ?string $albumId,
        ?string $eventDateFrom,
        ?string $eventDateTo,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        if ($childId !== null && trim($childId) !== '') {
            $this->assertActiveChildAccess($guardian, $childId);
        }

        if ($albumId !== null && trim($albumId) !== '') {
            $this->assertVisibleAlbumAccess($guardian, $albumId);
        }

        $query = Photo::query()
            ->select('photos.*')
            ->distinct()
            ->with(['album', 'taggedChildren'])
            ->join('albums', 'albums.id', '=', 'photos.album_id')
            ->join('photo_child_tags', 'photo_child_tags.photo_id', '=', 'photos.id')
            ->join('guardian_child', function ($join) use ($guardian): void {
                $join->on('guardian_child.child_id', '=', 'photo_child_tags.child_id')
                    ->where('guardian_child.guardian_id', $guardian->id)
                    ->whereNull('guardian_child.unlinked_at');
            })
            ->whereNotNull('photos.preview_path')
            ->where('photos.preview_status', 'ready');

        if ($childId !== null && trim($childId) !== '') {
            $query->where('guardian_child.child_id', $childId);
        }

        if ($albumId !== null && trim($albumId) !== '') {
            $query->where('photos.album_id', $albumId);
        }

        if ($eventDateFrom !== null && trim($eventDateFrom) !== '') {
            $query->whereDate('albums.event_date', '>=', $eventDateFrom);
        }

        if ($eventDateTo !== null && trim($eventDateTo) !== '') {
            $query->whereDate('albums.event_date', '<=', $eventDateTo);
        }

        return $query
            ->orderByDesc('photos.created_at')
            ->paginate($perPage, ['photos.*'], 'page', $page);
    }

    public function findPhotoDetail(Guardian $guardian, string $photoId): array
    {
        $photo = Photo::query()
            ->with(['album', 'taggedChildren.childClass'])
            ->where('id', $photoId)
            ->first();

        if ($photo === null) {
            throw new PhotoNotFoundException;
        }

        if (! $this->isPhotoVisibleToGuardian($guardian, $photo->id)) {
            throw new PhotoAccessDeniedException;
        }

        $visibleChildIds = $this->activeChildIds($guardian);

        return [
            'photo_id' => $photo->id,
            'album' => [
                'title' => $photo->album?->title,
                'event_date' => $photo->album?->event_date?->toDateString(),
            ],
            'price' => $photo->price,
            'preview_url' => $this->previewUrlFor($photo->preview_path),
            'tagged_children' => $photo->taggedChildren
                ->filter(fn ($child): bool => $visibleChildIds->contains($child->id))
                ->map(fn ($child): array => [
                    'child_id' => $child->id,
                    'name' => $child->name,
                    'class_name' => $child->childClass->name,
                ])
                ->values()
                ->all(),
        ];
    }

    public function refreshPreviewUrl(Guardian $guardian, string $photoId): array
    {
        $photo = Photo::query()->where('id', $photoId)->first();

        if ($photo === null) {
            throw new PhotoNotFoundException;
        }

        if (! $this->isPhotoVisibleToGuardian($guardian, $photoId)) {
            throw new PhotoAccessDeniedException;
        }

        return [
            'preview_url' => $this->previewUrlFor($photo->preview_path),
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
        ];
    }

    private function assertActiveChildAccess(Guardian $guardian, string $childId): void
    {
        $exists = GuardianChild::query()
            ->where('guardian_id', $guardian->id)
            ->where('child_id', $childId)
            ->whereNull('unlinked_at')
            ->exists();

        if (! $exists) {
            throw new PhotoAccessDeniedException;
        }
    }

    private function assertVisibleAlbumAccess(Guardian $guardian, string $albumId): void
    {
        $exists = Album::query()
            ->join('photos', 'photos.album_id', '=', 'albums.id')
            ->join('photo_child_tags', 'photo_child_tags.photo_id', '=', 'photos.id')
            ->join('guardian_child', function ($join) use ($guardian): void {
                $join->on('guardian_child.child_id', '=', 'photo_child_tags.child_id')
                    ->where('guardian_child.guardian_id', $guardian->id)
                    ->whereNull('guardian_child.unlinked_at');
            })
            ->where('albums.id', $albumId)
            ->whereNotNull('photos.preview_path')
            ->where('photos.preview_status', 'ready')
            ->exists();

        if (! $exists) {
            throw new PhotoAccessDeniedException;
        }
    }

    private function isPhotoVisibleToGuardian(Guardian $guardian, string $photoId): bool
    {
        return Photo::query()
            ->join('photo_child_tags', 'photo_child_tags.photo_id', '=', 'photos.id')
            ->join('guardian_child', function ($join) use ($guardian): void {
                $join->on('guardian_child.child_id', '=', 'photo_child_tags.child_id')
                    ->where('guardian_child.guardian_id', $guardian->id)
                    ->whereNull('guardian_child.unlinked_at');
            })
            ->where('photos.id', $photoId)
            ->whereNotNull('photos.preview_path')
            ->where('photos.preview_status', 'ready')
            ->exists();
    }

    public function previewUrlFor(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        /** @var FilesystemAdapter $filesystem */
        $filesystem = Storage::disk('s3');

        return $filesystem->temporaryUrl($path, now()->addMinutes(10));
    }

    public function activeChildIds(Guardian $guardian): Collection
    {
        return GuardianChild::query()
            ->where('guardian_id', $guardian->id)
            ->whereNull('unlinked_at')
            ->pluck('child_id');
    }
}
