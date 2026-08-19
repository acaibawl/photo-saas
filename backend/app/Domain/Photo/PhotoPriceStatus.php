<?php

namespace App\Domain\Photo;

enum PhotoPriceStatus: string
{
    case Set = 'set';
    case Unset = 'unset';

    public function label(): string
    {
        return match ($this) {
            self::Set => '価格設定済み',
            self::Unset => '未設定',
        };
    }
}
