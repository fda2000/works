<?php

namespace App\Domain\Notifications\Services\Providers;

use App\Domain\Notifications\Services\Provider;
use App\Models\User;

//TODO здесь будет фактическая реализация
class Sms extends Provider
{
    private array $users;

    public function resultOk(): array
    {
        return $this->users;
    }

    public function resultError(): array
    {
        return [];
    }

    /** @param User[] $users */
    public function send(array $users, string $text)
    {
        $this->users = $users;
    }

    /** @param User[] $users */
    public function checkDelivery(array $users)
    {
        $this->users = $users;
    }
}
