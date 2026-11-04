<?php

namespace App\Services\Homepage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Resolves image paths of homepage content to URLs.
 * Absolute URLs (e.g. previews of not yet saved uploads) are passed through unchanged.
 */
class HomepageImage
{
    public static function url(string $pathOrUrl): string
    {
        if (Str::startsWith($pathOrUrl, ['http://', 'https://', '/'])) {
            return $pathOrUrl;
        }

        return Storage::disk('public')->url($pathOrUrl);
    }
}
