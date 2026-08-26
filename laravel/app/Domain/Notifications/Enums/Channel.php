<?php

namespace App\Domain\Notifications\Enums;

use App\Domain\Notifications\Services\Provider;
use App\Domain\Notifications\Services\Providers\Email;
use App\Domain\Notifications\Services\Providers\Sms;

enum Channel: string
{
    case SMS = 'sms';
    case EMAIL = 'email';

    public function providerClass(): string
    {
        return match ($this) {
            self::SMS => Sms::class,
            self::EMAIL => Email::class,
        };
    }

    public function provider(): Provider
    {
        return app($this->providerClass());
    }
}
