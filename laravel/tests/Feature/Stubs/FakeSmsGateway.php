<?php

namespace Tests\Feature\Stubs;

use App\Domain\Notifications\Services\Provider;
use App\Models\User;

/**
 * Тестовый двойник (test double) реального SMS-шлюза.
 *
 * Наследуется от того же абстрактного Provider, что и реальный провайдер,
 * поэтому Sender работает с ним как с настоящим. Отличие: вместо отправки
 * во внешний шлюз он лишь записывает вызовы в память и возвращает
 * предсказуемый результат. Это позволяет прогонять интеграционную цепочку
 * без реальной отправки сообщений наружу.
 */
class FakeSmsGateway extends Provider
{
    public bool $isSingleUser = false;

    /** Записанные отправки: [ ['users' => User[], 'text' => string], ... ] */
    public array $sent = [];

    /** User[], которые считаем успешно отправленными */
    private array $ok = [];

    /** User[], которые считаем ошибочными (dropped) */
    private array $errors = [];

    /**
     * Настроить, кто успешен, а кто ошибочен, до запуска цепочки.
     */
    public function fakeResults(array $okUsers = [], array $errorUsers = []): void
    {
        $this->ok = $okUsers;
        $this->errors = $errorUsers;
    }

    public function resultOk(): array
    {
        return $this->ok;
    }

    public function resultError(): array
    {
        return $this->errors;
    }

    public function send(array $users, string $text)
    {
        // Не отправляем наружу - только фиксируем вызов.
        $this->sent[] = ['users' => $users, 'text' => $text];
    }

    public function checkDelivery(array $users)
    {
        // Имитация проверки доставки: ничего не отправляем.
        $this->ok = $users;
        $this->errors = [];
    }

    /**
     * Проверка, что провайдер действительно был вызван в ходе цепочки.
     */
    public function wasCalled(): bool
    {
        return $this->sent !== [];
    }
}