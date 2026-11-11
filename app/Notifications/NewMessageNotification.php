<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Notifications\Channels\ZohoWhatsappChannel;
use App\Notifications\Messages\WhatsAppMessage;
use NotificationChannels\WebPush\WebPushChannel;

class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $message;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($message)
    {
        //
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     *
     * Channels are tried in priority order; each self-skips when
     * unavailable (no push subscription, no WhatsApp opt-in, no email).
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return [WebPushChannel::class, ZohoWhatsappChannel::class, 'mail', 'database'];
    }

    /**
     * Get the WhatsApp representation of the notification (Zoho CPaaS
     * template key + variables). Only reached for opted-in notifiables.
     *
     * @param  mixed  $notifiable
     * @return \App\Notifications\Messages\WhatsAppMessage
     */
    public function toWhatsApp($notifiable)
    {
        return new WhatsAppMessage(
            template: 'school_notice',
            variables: ['message' => (string) $this->message],
            locale: data_get($notifiable, 'locale') ?: config('app.locale'),
        );
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
            //'type'  =>  'newmessage', 
            'data'  =>  $this->message,
        ];
    }
}
