<?php

namespace App\Domain\Kindergarten;

use App\Models\Kindergarten;
use Illuminate\Support\Str;

final class KindergartenSlugGenerator
{
    public function generateUniqueFrom(string $name): string
    {
        $base = Str::slug($name) ?: 'kindergarten';
        $slug = $base;

        while (Kindergarten::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}
