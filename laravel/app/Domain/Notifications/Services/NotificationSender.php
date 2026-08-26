<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Enums\Channel;
use App\Domain\Notifications\Enums\Priority;
use App\Domain\Notifications\Jobs\Sender;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Models\NotificationRecipient;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class NotificationSender
{
    /** @var User[] */
    private array $users = [];
    private ?Priority $priority = null;
    private ?string $externalId = null;

    public function __construct(
        private readonly Channel $channel
    )
    {
    }

    public static function create(Channel $channel): static
    {
        return new self($channel);
    }

    public function setPriority(?Priority $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    /** @param User[] $users */
    public function setUsers(array $users): static
    {
        $this->users = $users;
        return $this;
    }

    public function setExternalId(mixed $external_id): static
    {
        $this->externalId = $external_id;
        return $this;
    }

    /** @return Notification[] */
    public function send(string $text): array
    {
        $notifications = [];
        if (!$this->channel->provider()->isSingleUser) {
            $notification = $this->createNotification($text, $this->users);
            $notifications[] = $notification;

        } else {
            foreach ($this->users as $user) {
                $notification = $this->createNotification($text, [$user]);
                $notifications[] = $notification;
            }
        }

        return $notifications;
    }

    /** @param User[] $users */
    private function createNotification(string $text, array $users): Notification
    {
        $notification = new Notification;
        $notification->channel = $this->channel;
        $notification->message = $text;
        $notification->idempotency_key = $this->getIdempotencyKey();
        $notification->priority = $this->priority ?: Priority::DEFAULT;

        $links = [];
        foreach ($users as $user) {
            $link = new NotificationRecipient;
            $link->user()->associate($user);
            $link->notification()->associate($notification);
            $links[] = $link;
        }

        return $this->save($links, $notification);
    }

    /**
     * @param NotificationRecipient[] $links
     * @param Notification $notification
     * @return Notification
     */
    private function save(array $links, Notification $notification): Notification
    {
        try {
            DB::transaction(function () use ($links, $notification) {
                $notification->save();
                $notification->recipients()->saveMany($links);
            });
            Sender::dispatchFor($notification);
        } catch (UniqueConstraintViolationException $e) {
            $notification = Notification::where('idempotency_key', $notification->idempotency_key)->first();
        }

        return $notification;
    }

    /** Возможен более сложный алгоритм */
    private function getIdempotencyKey(): ?string
    {
        return $this->externalId;
    }
}
