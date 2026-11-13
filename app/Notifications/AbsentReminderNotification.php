<?php

namespace App\Notifications;

use App\Models\Reminder;
use App\Notifications\Channels\ZohoWhatsappChannel;
use App\Notifications\Messages\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Sent to a parent when their child is marked absent. Replaces the legacy
 * AbsentReminderMail mailable: the same Reminder record drives all four
 * channels of the ladder (web push -> WhatsApp -> mail -> database), each
 * channel self-skipping when unavailable.
 */
class AbsentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Reminder $reminder;

    public function __construct(Reminder $reminder)
    {
        $this->reminder = $reminder;
    }

    /**
     * Channels in priority order; each self-skips when unavailable
     * (no push subscription, no WhatsApp opt-in, no email). Anonymous
     * (mail-route-only) notifiables get mail alone: they have no push
     * subscriptions, no WhatsApp opt-in record and no database store.
     */
    public function via($notifiable): array
    {
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return ['mail'];
        }

        return [WebPushChannel::class, ZohoWhatsappChannel::class, 'mail', 'database'];
    }

    public function toWhatsApp($notifiable): WhatsAppMessage
    {
        return new WhatsAppMessage(
            template: 'absentee_alert',
            variables: [
                'student_name' => $this->studentName(),
                'date' => $this->absenceDate(),
            ],
            locale: $this->locale($notifiable),
        );
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title(__('Absentee Alert', [], $this->locale($notifiable)))
            ->icon('/favicon.ico')
            ->body($this->reminder->message)
            ->data([
                'type' => 'absentee_alert',
                'student_name' => $this->studentName(),
                'date' => $this->absenceDate(),
            ])
            ->options(['TTL' => 86400]);
    }

    public function toMail($notifiable): MailMessage
    {
        $locale = $this->locale($notifiable);

        return (new MailMessage)
            ->subject(__('Absentee Alert', [], $locale))
            ->markdown('emails.absent_reminder', [
                'studentName' => $this->studentName(),
                'date' => $this->absenceDate(),
                'message' => $this->reminder->message,
                'locale' => $locale,
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'absentee_alert',
            'student_name' => $this->studentName(),
            'date' => $this->absenceDate(),
            'message' => $this->reminder->message,
        ];
    }

    protected function locale($notifiable): string
    {
        return data_get($notifiable, 'userprofile.locale')
            ?: (data_get($notifiable, 'locale') ?: config('app.locale'));
    }

    /**
     * Structured payload stored on the Reminder by
     * EventProcess::sendToAttendanceReminder(); legacy rows carry the
     * plain string 'absent_message' and fall back to empty values.
     */
    protected function studentName(): string
    {
        return is_array($this->reminder->data)
            ? (string) ($this->reminder->data['student_name'] ?? '')
            : '';
    }

    protected function absenceDate(): string
    {
        if (is_array($this->reminder->data) && ! empty($this->reminder->data['date'])) {
            return (string) $this->reminder->data['date'];
        }

        return $this->reminder->executed_at
            ? date('d-m-Y', strtotime((string) $this->reminder->executed_at))
            : '';
    }
}
