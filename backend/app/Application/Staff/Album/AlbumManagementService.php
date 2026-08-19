<?php

namespace App\Application\Staff\Album;

use App\Models\Album;
use App\Models\KindergartenStaff;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AlbumManagementService
{
    public function listAlbums(KindergartenStaff $actor, int $page, int $perPage): LengthAwarePaginator
    {
        return Album::query()
            ->where('kindergarten_id', $actor->kindergarten_id)
            ->orderByDesc('event_date')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function createAlbum(KindergartenStaff $actor, string $title, string $eventDate): array
    {
        $album = Album::create([
            'kindergarten_id' => $actor->kindergarten_id,
            'title' => trim($title),
            'event_date' => $eventDate,
        ]);

        return [
            'id' => $album->id,
            'kindergarten_id' => $album->kindergarten_id,
            'title' => $album->title,
            'event_date' => $album->event_date->toDateString(),
            'created_at' => $album->created_at?->toIso8601String(),
        ];
    }
}
