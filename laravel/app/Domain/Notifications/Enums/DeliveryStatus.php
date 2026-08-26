<?php

namespace App\Domain\Notifications\Enums;

enum DeliveryStatus: string
{
    const DEFAULT = self::QUEUED;

    case QUEUED = 'queued';
    case SENDING = 'sending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case DROPPED = 'dropped';
}
