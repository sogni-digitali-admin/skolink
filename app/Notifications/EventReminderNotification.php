<?php

namespace App\Notifications;

use App\Models\Reminder;
use App\Notifications\Channels\ZohoWhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Event reminder sent to parents/staff. Replaces the legacy ReminderMail
 * mailable. No toWhatsApp(): routine reminders stay on the free channels
 * (web push / mail / database); the Zoho CPaaS channel self-skips.
 */
class EventReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Reminder $reminder;

    public function __construct(Reminder $reminder)
    {
        $this->reminder = $reminder;
    }

    /**
     * Channels in priority order; each self-skips when unavailable.
     * Anonymous (mail-route-only) notifiables get mail alone.
     */
    public function via($notifiable): array
    {
        if ($notifiable instanceof \Illuminate\Notifications\AnonymousNotifiable) {
            return ['mail'];
        }

        return [WebPushChannel::class, ZohoWhatsappChannel::class, 'mail', 'database'];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $event = $this->reminder->events;

        return (new WebPushMessage)
            ->title(__('Event Reminder', [], $this->locale($notifiable)))
            ->icon('/favicon.ico')
            ->body($event ? (string) $event->title : (string) $this->reminder->subject)
            ->data([
                'type' => 'event_reminder',
                'event_id' => $this->reminder->entity_id,
            ])
            ->options(['TTL' => 86400]);
    }

    public function toMail($notifiable): MailMessage
    {
        $locale = $this->locale($notifiable);
        $event = $this->reminder->events;

        return (new MailMessage)
            ->subject(__('Event Reminder', [], $locale))
            ->markdown('emails.event_reminder', [
                'schoolName' => optional($this->reminder->school)->name,
                'title' => optional($event)->title,
                'description' => optional($event)->description,
                'location' => optional($event)->location,
                'startDate' => optional($event)->start_date,
                'endDate' => optional($event)->end_date,
                'locale' => $locale,
            ]);
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'event_reminder',
            'event_id' => $this->reminder->entity_id,
            'subject' => $this->reminder->subject,
        ];
    }

    protected function locale($notifiable): string
    {
        return data_get($notifiable, 'userprofile.locale')
            ?: (data_get($notifiable, 'locale') ?: config('app.locale'));
    }
}
