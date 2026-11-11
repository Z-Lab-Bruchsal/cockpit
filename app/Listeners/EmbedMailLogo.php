<?php

namespace App\Listeners;

use App\Models\CompanySetting;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Storage;

/**
 * Embeds the company logo as inline attachment so mail clients that block external images still show it.
 */
class EmbedMailLogo
{
    public const CONTENT_ID = 'company-logo';

    /**
     * Replace the public logo URL in the HTML body with a reference to the embedded file.
     */
    public function handle(MessageSending $event): void
    {
        $html = $event->message->getHtmlBody();
        $logoPath = CompanySetting::current()->logo_path;

        if (! is_string($html) || ! $logoPath) {
            return;
        }

        $disk = Storage::disk('public');
        $logoUrl = e($disk->url($logoPath));

        if (! str_contains($html, $logoUrl) || ! $disk->exists($logoPath)) {
            return;
        }

        $contentId = self::CONTENT_ID.'.'.pathinfo($logoPath, PATHINFO_EXTENSION);

        $event->message
            ->embedFromPath($disk->path($logoPath), $contentId, $disk->mimeType($logoPath) ?: null)
            ->html(str_replace($logoUrl, 'cid:'.$contentId, $html));
    }
}
