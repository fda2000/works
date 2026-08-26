<?php

namespace App\Domain\Notifications\Services;

use App\Models\User;

abstract class Provider
{
    public bool $isSingleUser = false;

    /** @return User[] */
    abstract public function resultOk(): array;

    /** @return User[] */
    abstract public function resultError(): array;

    /** @param User[] $users */
    abstract public function send(array $users, string $text);

    /** @param User[] $users */
    abstract public function checkDelivery(array $users);
}
