<?php

namespace App\Models;

use Database\Factories\NewsPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Feed\Feedable;
use Spatie\Feed\FeedItem;

#[Fillable(['title', 'slug', 'teaser', 'image_path', 'image_alt', 'content', 'published_at'])]
class NewsPost extends Model implements Feedable
{
    /** @use HasFactory<NewsPostFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Posts whose publication date has been reached, newest first.
     *
     * @param  Builder<NewsPost>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->latest('id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    public function url(): string
    {
        return route('homepage.news.show', $this->slug);
    }

    public function toFeedItem(): FeedItem
    {
        return FeedItem::create()
            ->id($this->url())
            ->title($this->title)
            ->summary($this->teaser ?? '')
            ->updated($this->published_at)
            ->link($this->url())
            ->authorName(CompanySetting::current()->homepageTitle());
    }

    /**
     * @return Collection<int, NewsPost>
     */
    public static function getFeedItems(): Collection
    {
        return static::query()->published()->limit(20)->get();
    }
}
