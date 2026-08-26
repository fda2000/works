<?php

namespace Tests\Unit;

use App\Domain\Notifications\Enums\Channel;
use App\Domain\Notifications\Enums\DeliveryStatus;
use App\Domain\Notifications\Enums\Priority;
use App\Domain\Notifications\Jobs\Sender;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Models\NotificationRecipient;
use App\Domain\Notifications\Services\NotificationSender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;
    private User $user2;
    private Channel $channel;
    private NotificationSender $creator;
    private Notification $notification;
    private NotificationRecipient $notificationRecipient1;
    private NotificationRecipient $notificationRecipient2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();
        $this->channel = Channel::EMAIL;
        $this->creator = NotificationSender::create($this->channel);

        $this->notification = Notification::create([
            'channel' => $this->channel,
            'message' => 'test',
            'priority' => Priority::DEFAULT,
        ]);

        $this->notificationRecipient1 = new NotificationRecipient;
        $this->notificationRecipient1->user()->associate($this->user1);
        $this->notificationRecipient1->notification()->associate($this->notification);
        $this->notificationRecipient1->save();

        $this->notificationRecipient2 = new NotificationRecipient;
        $this->notificationRecipient2->user()->associate($this->user2);
        $this->notificationRecipient2->notification()->associate($this->notification);
        $this->notificationRecipient2->save();
    }

    public function test_idempotency(): void
    {
        Queue::fake();
        $this->creator->setUsers([$this->user1]);

        $notification1 = current($this->creator->send('test'));
        $this->assertIsObject($notification1);
        $notification2 = current($this->creator->send('test'));
        $this->assertIsObject($notification2);
        $this->assertNotEquals($notification1->id, $notification2->id);

        $this->creator->setExternalId('idempotency_key');
        $notification1 = current($this->creator->send('test'));
        $this->assertIsObject($notification1);
        $notification2 = current($this->creator->send('test'));
        $this->assertIsObject($notification2);
        $this->assertEquals($notification1->id, $notification2->id);

        Queue::assertPushed(Sender::class, 3);
    }

    public function test_sender_ok(): void
    {
        Queue::fake();

        $provider = Mockery::mock($this->channel->providerClass());
        $this->instance($this->channel->providerClass(), $provider);
        $provider->shouldReceive('resultOk')->andReturn([$this->user1, $this->user2]);
        $provider->shouldReceive('resultError')->andReturn([]);
        $provider->shouldReceive('send');

        $job = Mockery::mock(Sender::class, [$this->notification->id])->makePartial();
        $job->shouldNotReceive('release');
        $job->handle();

        $this->notificationRecipient1->refresh();
        $this->assertEquals(DeliveryStatus::SENT, $this->notificationRecipient1->status);
        $this->notificationRecipient2->refresh();
        $this->assertEquals(DeliveryStatus::SENT, $this->notificationRecipient2->status);
    }

    public function test_sender_errors(): void
    {
        Queue::fake();

        $provider = Mockery::mock($this->channel->providerClass());
        $this->instance($this->channel->providerClass(), $provider);
        $provider->shouldReceive('resultOk')->andReturn([$this->user1]);
        $provider->shouldReceive('resultError')->andReturn([$this->user2]);
        $provider->shouldReceive('send');

        $job = Mockery::mock(Sender::class, [$this->notification->id])->makePartial();
        $job->shouldNotReceive('release');
        $job->handle();

        $this->notificationRecipient1->refresh();
        $this->assertEquals(DeliveryStatus::SENT, $this->notificationRecipient1->status);
        $this->notificationRecipient2->refresh();
        $this->assertEquals(DeliveryStatus::DROPPED, $this->notificationRecipient2->status);
    }

    public function test_sender_dropped(): void
    {
        Queue::fake();
        $provider = Mockery::mock($this->channel->providerClass());
        $this->instance($this->channel->providerClass(), $provider);
        $provider->shouldReceive('resultOk')->andReturn([$this->user1]);
        $provider->shouldReceive('resultError')->andReturn([]);
        $provider->shouldReceive('send');

        $job = Mockery::mock(Sender::class, [$this->notification->id])->makePartial();
        $job->shouldReceive('release')->once();
        $job->handle();

        $this->notificationRecipient1->refresh();
        $this->assertEquals(DeliveryStatus::SENT, $this->notificationRecipient1->status);
        $this->notificationRecipient2->refresh();
        $this->assertEquals(DeliveryStatus::QUEUED, $this->notificationRecipient2->status);
    }
}
