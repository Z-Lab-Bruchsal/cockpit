<?php

namespace AlexanderGabriel\FilamentMailTestMode\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property bool $enabled
 * @property string|null $recipient
 * @property int|null $activated_by
 * @property Carbon|null $activated_at
 */
class MailTestModeSetting extends Model
{
    protected $table = 'mail_test_mode_settings';

    protected $fillable = [
        'enabled',
        'recipient',
        'activated_by',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['enabled' => false]);
    }
}
