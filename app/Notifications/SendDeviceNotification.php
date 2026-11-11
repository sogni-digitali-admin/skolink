<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class SendDeviceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public array $data;

    /**
     * Legacy FCM device token. Kept in the constructor signature for the
     * existing call sites; web push routes via the user's push_subscriptions
     * instead, so the value is unused.
     */
    public ?string $token;

    public function __construct(array $data, ?string $token = null)
    {
        $this->queue = 'notification';
        $this->data  = $data;
        $this->token = $token;
    }

    public function via($notifiable): array
    {
        return [WebPushChannel::class, 'database'];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $type    = $this->data['type'] ?? 'Notification';
        $message = $this->data['message'] ?? '';

        return (new WebPushMessage)
            ->title($type)
            ->icon('/favicon.ico')
            ->body($message)
            ->data([
                'type'    => $type,
                'message' => $message,
            ])
            ->options(['TTL' => 1200]);
    }

    public function toArray($notifiable): array
    {
        return [
            'type'    => $this->data['type'] ?? 'Notification',
            'message' => $this->data['message'] ?? '',
        ];
    }
}
