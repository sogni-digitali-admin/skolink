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
     * Optional chat context: the sender and conversation the message
     * belongs to. NULL for the original school-notice use.
     *
     * @var \App\Models\User|null
     */
    public $sender;

    /**
     * @var \App\Models\Conversation|null
     */
    public $conversation;

    /**
     * Create a new notification instance.
     *
     * @param string $message
     * @param \App\Models\User|null $sender
     * @param \App\Models\Conversation|null $conversation
     * @return void
     */
    public function __construct($message, $sender = null, $conversation = null)
    {
        //
        $this->message = $message;
        $this->sender = $sender;
        $this->conversation = $conversation;
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
            locale: $this->locale($notifiable),
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
        $locale = $this->locale($notifiable);

        return (new MailMessage)
            ->subject(__('New Message', [], $locale))
            ->markdown('emails.new_message', [
                'message' => (string) $this->message,
                'locale' => $locale,
            ]);
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
            'sender_name'       => $this->sender ? $this->sender->name : null,
            'conversation_uuid' => $this->conversation ? $this->conversation->uuid : null,
        ];
    }

    /**
     * Resolve the notifiable's locale: userprofiles.locale first,
     * falling back to config('app.locale').
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function locale($notifiable)
    {
        return data_get($notifiable, 'userprofile.locale')
            ?: (data_get($notifiable, 'locale') ?: config('app.locale'));
    }
}
