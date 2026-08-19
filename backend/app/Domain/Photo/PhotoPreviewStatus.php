<?php

namespace App\Domain\Photo;

enum PhotoPreviewStatus: string
{
    case Queued = 'queued';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => '処理待ち',
            self::Ready => '準備完了',
            self::Failed => '処理失敗',
        };
    }
}
