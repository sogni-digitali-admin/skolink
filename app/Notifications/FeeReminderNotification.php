<?php

namespace App\Notifications;

use App\Notifications\Channels\ZohoWhatsappChannel;
use App\Notifications\Messages\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Fee payment reminder sent to a student's parents. High-value event, so
 * the full ladder applies including the WhatsApp `fee_reminder` template
 * for opted-in parents.
 */
class FeeReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $feeName;

    public string $dueDate;

    public string $studentName;

    public function __construct(string $feeName, string $dueDate, string $studentName = '')
    {
        $this->feeName = $feeName;
        $this->dueDate = $dueDate;
        $this->studentName = $studentName;
    }

    /**
     * Channels in priority order; each self-skips when unavailable
     * (no push subscription, no WhatsApp opt-in, no email).
     */
    public function via($notifiable): array
    {
        return [WebPushChannel::class, ZohoWhatsappChannel::class, 'mail', 'database'];
    }

    public function toWhatsApp($notifiable): WhatsAppMessage
    {
        return new WhatsAppMessage(
            template: 'fee_reminder',
            variables: [
                'fee_name' => $this->feeName,
                'student_name' => $this->studentName,
                'due_date' => $this->dueDate,
            ],
            locale: $this->locale($notifiable),
        );
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title(__('Fee Payment Reminder', [], $this->locale($notifiable)))
            ->icon('/favicon.ico')
            ->body(__('The payment for :fee is pending. Last date for payment: :date.', [
                'fee' => $this->feeName,
                'date' => $this->dueDate,
            ], $this->locale($notifiable)))
            ->data([
                'type' => 'fee_reminder',
                'fee_name' => $this->feeName,
                'due_date' => $this->dueDate,
            ])
            ->options(['TTL' => 86400]);
    }

    public function toMail($notifiable): MailMessage
    {
        $locale = $this->locale($notifiable);

        return (new MailMessage)
            ->subject(__('Fee Payment Reminder', [], $locale))
            ->markdown('emails.fee_reminder', [
                'feeName' => $this->feeName,
                'dueDate' => $this->dueDate,
                'studentName' => $this->studentName,
                'locale' => $locale,
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'fee_reminder',
            'fee_name' => $this->feeName,
            'student_name' => $this->studentName,
            'due_date' => $this->dueDate,
        ];
    }

    protected function locale($notifiable): string
    {
        return data_get($notifiable, 'userprofile.locale')
            ?: (data_get($notifiable, 'locale') ?: config('app.locale'));
    }
}
