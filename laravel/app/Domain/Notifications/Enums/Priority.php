<?php

namespace App\Domain\Notifications\Enums;

enum Priority: string
{
    const DEFAULT = self::NORMAL;

    case CRITICAL = 'critical';
    case NORMAL = 'default';
    case MARKETING = 'marketing';
}
