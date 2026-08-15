<?php

namespace App\Application\Staff\Album;

use App\Models\Album;
use App\Models\KindergartenStaff;

final class AlbumManagementService
{
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
