<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Enums\Channel;
use App\Domain\Notifications\Enums\Priority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    protected $casts = [
        'channel' => Channel::class,
        'priority' => Priority::class,
    ];

    protected $fillable = ['channel', 'priority', 'message'];

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }
}
