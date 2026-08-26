<?php

namespace App\Domain\Notifications\Jobs;

use App\Domain\Notifications\Enums\DeliveryStatus;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Models\NotificationRecipient;
use App\Domain\Notifications\Services\Provider;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;

#[WithoutRelations]
class Sender implements ShouldQueue
{
    const DELAY_RETRY = 30;

    use Queueable;
    public int $tries = 6;       // 1 первый + 5 повторов (как COUNT_RETRY)
    public int $timeout = self::DELAY_RETRY;     // опционально

    private Provider $provider;
    private int $notificationId;
    private Notification $notification;

    public function __construct(int $notificationId)
    {
        $this->notificationId = $notificationId;
    }

    public static function dispatchFor(Notification $notification): PendingDispatch
    {
        return dispatch(new self($notification->id))
            ->onQueue($notification->priority->value);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->notification = Notification::with('recipients.user')->findOrFail($this->notificationId);
        //Log::debug('Sender begin');
        /** @var Provider $provider */
        $this->provider = $this->notification->channel->provider();
        /** @var NotificationRecipient[][] $byStatus */
        $byStatus = [];
        /** @var NotificationRecipient $recipient */
        foreach ($this->notification->recipients as $recipient) {
            /** @var DeliveryStatus $status */
            $status = $recipient->status;
            $byStatus[$status->value][] = $recipient;
        }

        foreach ($byStatus as $statusId => $recipients) {
            $status = DeliveryStatus::from($statusId);
            match ($status) {
                DeliveryStatus::QUEUED => $this->runSend($recipients),
                DeliveryStatus::SENT => $this->runCheckDelivery($recipients),
                default => null,
            };
        }
    }

    /** @param NotificationRecipient[] $recipients */
    private function runSend(array $recipients)
    {
        $toSend = [];
        foreach ($recipients as $recipient) {
            if ($recipient->status != DeliveryStatus::QUEUED) {
                continue;
            }

            // Атомарное CAS-резервирование: только один worker переведёт
            // получателя QUEUED -> SENDING. Иначе повторный retry отправит дубликат.
            $reserved = NotificationRecipient::query()
                ->whereKey($recipient->id)
                ->where('status', DeliveryStatus::QUEUED->value)
                ->update(['status' => DeliveryStatus::SENDING->value]);

            if ($reserved === 0) {
                continue; // уже забран другим worker'ом / предыдущим retry
            }

            $recipient->status = DeliveryStatus::SENDING;
            $toSend[] = $recipient;
        }

        if (empty($toSend)) {
            return;
        }

        /** @var User[] $users */
        $users = array_map(fn($recipient) => $recipient->user, $toSend);
        $this->provider->send($users, $this->notification->message);
        $this->processResult(DeliveryStatus::SENT);
    }

    /** @param NotificationRecipient[] $recipients */
    private function runCheckDelivery(array $recipients)
    {
        /** @var User[] $users */
        $users = array_map(fn($recipient) => $recipient->user, $recipients);
        $this->provider->checkDelivery($users);
        $this->processResult(DeliveryStatus::DELIVERED);
    }

    private function processResult(DeliveryStatus $nextStatus)
    {
        $okUserIds = array_map(fn($user) => $user->id, $this->provider->resultOk());
        $errorUserIds = array_map(fn($user) => $user->id, $this->provider->resultError());
        $notAllProcessed = false;
        /** @var NotificationRecipient $recipient */
        foreach ($this->notification->recipients as $recipient) {
            $recipient->refresh();
            if (in_array($recipient->user->id, $errorUserIds)) {
                $recipient->status = DeliveryStatus::DROPPED;
            } elseif (in_array($recipient->user->id, $okUserIds)) {
                $recipient->status = $nextStatus;
            } else {
                //Если драйвер не вернул ответ для этого получателя - повторить отправку
                $recipient->status = DeliveryStatus::QUEUED;
                $notAllProcessed = true;
            }

            $recipient->save();
        }

        if ($notAllProcessed) {
            $this->retry();
        }
    }

    private function retry()
    {
        $this->release(self::DELAY_RETRY);
    }

    public function failed(): void
    {
        NotificationRecipient::query()
            ->where('notification_id', $this->notificationId)
            ->where('status', DeliveryStatus::QUEUED->value)
            ->update(['status' => DeliveryStatus::DROPPED->value]);
    }
}
