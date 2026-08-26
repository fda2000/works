<?php

namespace Tests\Feature;

use App\Domain\Notifications\Enums\Channel;
use App\Domain\Notifications\Enums\DeliveryStatus;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Services\NotificationSender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Stubs\FakeSmsGateway;
use Tests\TestCase;

/**
 * Интеграционный тест полной цепочки:
 * HTTP-запрос -> запись уведомления и получателей в БД ->
 * очередь (sync) -> Sender::handle -> вызов провайдера-заглушки ->
 * изменение статуса в БД.
 */
class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;
    private User $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();
    }

    /**
     * Полная цепочка: отправка SMS -> джоб из очереди -> вызов провайдера ->
     * статус SENT в БД. Провайдер Sms::resultOk() возвращает всех пользователей,
     * поэтому ожидаем статус SENT без ретрая.
     */
    public function test_sms_delivery_chain_sets_sent_in_database(): void
    {
        NotificationSender::create(Channel::SMS)
            ->setUsers([$this->user1, $this->user2])
            ->send('Ваш код доступа: 123456');

        // QUEUE_CONNECTION=sync: Sender выполнился синхронно при dispatch.
        // Провайдер Sms вернул успех -> статус SENT.

        $this->assertDatabaseHas('notification_recipients', [
            'user_id' => $this->user1->id,
            'status' => DeliveryStatus::SENT->value,
        ]);
        $this->assertDatabaseHas('notification_recipients', [
            'user_id' => $this->user2->id,
            'status' => DeliveryStatus::SENT->value,
        ]);

        $this->assertDatabaseHas('notifications', [
            'channel' => Channel::SMS->value,
            'message' => 'Ваш код доступа: 123456',
        ]);
    }

    /**
     * Полная цепочка для Email: провайдер Email::resultError() возвращает всех,
     * поэтому ожидаем статус DROPPED (несуществующий адрес и т.п.).
     */
    public function test_email_delivery_chain_sets_dropped_in_database(): void
    {
        NotificationSender::create(Channel::EMAIL)
            ->setUsers([$this->user1])
            ->send('Промо-рассылка');

        // Провайдер Email - заглушка, имитирует ошибку доставки на всех.
        $this->assertDatabaseHas('notification_recipients', [
            'user_id' => $this->user1->id,
            'status' => DeliveryStatus::DROPPED->value,
        ]);
    }

    /**
     * Весь путь через реальный HTTP-эндпоинт: запрос -> валидация ->
     * создание уведомления -> джоб -> статус в БД. Проверяем и ответ API.
     *
     * Маршрут защищён middleware auth:sanctum, но тест не зависит от
     * реальных токенов: $this->actingAs() имитирует аутентифицированного
     * пользователя, подменяя guard без обращения к таблице personal_access_tokens.
     */
    public function test_http_send_endpoint_full_chain(): void
    {
        $response = $this->actingAs($this->user1)->postJson('/api/message/send', [
            'ids' => [$this->user1->id, $this->user2->id],
            'channel' => Channel::SMS->value,
            'text' => 'Срочное изменение маршрута',
            'priority' => 'critical',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['notifications' => []]);

        $notificationIds = $response->json('notifications');
        $this->assertCount(1, $notificationIds);

        $recipients = DB::table('notification_recipients')
            ->where('notification_id', $notificationIds[0])
            ->get();

        $this->assertCount(2, $recipients);
        foreach ($recipients as $recipient) {
            $this->assertEquals(DeliveryStatus::SENT->value, $recipient->status);
        }
    }

    /**
     * Контрольная проверка: статус по умолчанию = queued, а после
     * обработки через очередь меняется на sent/delivered. Доказывает,
     * что статус реально изменился в БД под действием джоба.
     */
    public function test_recipient_goes_from_queued_to_sent(): void
    {
        $notification = Notification::create([
            'channel' => Channel::SMS->value,
            'message' => 'test',
        ]);
        $recipient = new \App\Domain\Notifications\Models\NotificationRecipient;
        $recipient->user()->associate($this->user1);
        $recipient->notification()->associate($notification);
        $recipient->status = DeliveryStatus::QUEUED;
        $recipient->save();

        // До запуска джоба - queued.
        $this->assertDatabaseHas('notification_recipients', [
            'user_id' => $this->user1->id,
            'status' => DeliveryStatus::QUEUED->value,
        ]);

        // Запускаем джоб напрямую из очереди (имитация worker'а).
        (new \App\Domain\Notifications\Jobs\Sender($notification->id))->handle();

        $this->assertDatabaseHas('notification_recipients', [
            'user_id' => $this->user1->id,
            'status' => DeliveryStatus::SENT->value,
        ]);
    }

    /**
     * Демонстрация подмены реального внешнего шлюза тестовым двойником.
     *
     * Если бы Sms был реальной реализацией (отправлял HTTP-запросы наружу),
     * тест всё равно должен прогонять всю цепочку без реальной отправки.
     * Решение: Channel::provider() берёт провайдер из контейнера приложения,
     * поэтому мы подменяем привязку на FakeSmsGateway через $this->instance().
     * Весь остальной конвейер (БД, очередь/sync, Sender::handle) - реальный.
     */
    public function test_substitute_real_gateway_with_fake_via_container(): void
    {
        $fake = new FakeSmsGateway;
        // user1 успешен, user2 - ошибка шлюза.
        $fake->fakeResults([$this->user1], [$this->user2]);

        // Подменяем в контейнере привязку провайдера SMS на тестовый двойник.
        $this->instance(\App\Domain\Notifications\Services\Providers\Sms::class, $fake);

        // Прогоняем полную цепочку.
        NotificationSender::create(Channel::SMS)
            ->setUsers([$this->user1, $this->user2])
            ->send('Тест подмены шлюза');

        // 1) Провайдер был вызван, но реальной отправки не было (только запись в память).
        $this->assertTrue($fake->wasCalled());
        $this->assertSame('Тест подмены шлюза', $fake->sent[0]['text']);

        // 2) Статусы в БД сформированы на основе результата фейкового шлюза.
        $this->assertDatabaseHas('notification_recipients', [
            'user_id' => $this->user1->id,
            'status' => DeliveryStatus::SENT->value,
        ]);
        $this->assertDatabaseHas('notification_recipients', [
            'user_id' => $this->user2->id,
            'status' => DeliveryStatus::DROPPED->value,
        ]);
    }

    /**
     * Защита эндпоинта middleware auth:sanctum: запрос без токена/сессии
     * должен быть отклонён (401). Тест проверяет работу защиты маршрута,
     * не завися от каких-либо реальных токенов.
     */
    public function test_http_send_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/api/message/send', [
            'ids' => [$this->user1->id],
            'channel' => Channel::SMS->value,
            'text' => 'Без авторизации',
        ]);

        $response->assertUnauthorized();
    }
}