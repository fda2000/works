<?php

namespace App\Http\Controllers;

use App\Domain\Notifications\Enums\Channel;
use App\Domain\Notifications\Enums\Priority;
use App\Domain\Notifications\Models\NotificationRecipient;
use App\Domain\Notifications\Services\NotificationSender;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use SortDirection;

class MessageController extends Controller
{

    public function send(Request $request): array
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => Rule::exists(User::class, 'id'),

            'channel' => ['required', Rule::enum(Channel::class)],
            'priority' => Rule::enum(Priority::class),

            'text' => 'string|required',
            'external_id' => 'string',
        ]);


        $users = User::query()->whereKey($validated['ids'])->get()->all();
        $channel = Channel::from($validated['channel']);
        $priority = $validated['priority'] ?? null;
        $priority = $priority ? Priority::from($priority) : null;

        $notifications = NotificationSender::create($channel)
            ->setPriority($priority)
            ->setUsers($users)
            ->setExternalId($validated['external_id'] ?? null)
            ->send($validated['text']);

        $ids = array_map(
            fn($notification) => $notification->id,
            $notifications
        );

        return ['notifications' => $ids];
    }

    public function status(User $user): array
    {
        /** @var NotificationRecipient[] $notifications */
        $notifications = $user->notificationRecipients()
            ->with('notification')
            ->orderBy('updated_at', SortDirection::Descending)
            ->get();

        $json = [];
        foreach ($notifications as $notification) {
            $json[] = [
                'notification_id' => $notification->notification->id,
                'channel' => $notification->notification->channel,
                'created' => $notification->created_at,
                'message' => $notification->notification->message,
                'status' => $notification->status,
                'updated' => $notification->updated_at,
            ];
        }

        return $json;
    }
}
