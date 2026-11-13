<?php

namespace App\Listeners;

use App\Events\ReminderMailEvent;
use App\Notifications\EventReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class ReminderMailListener implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * Sends the event reminder through the notification ladder
     * (web push -> mail -> database) to the user matching the
     * reminder recipient. Falls back to an on-demand mail
     * notification when no user account matches the address.
     *
     * @param  ReminderMailEvent  $event
     * @return void
     */
    public function handle(ReminderMailEvent $event)
    {
        $user = $event->reminder->user;

        if ($user) {
            $user->notify(new EventReminderNotification($event->reminder));

            return;
        }

        Notification::route('mail', $event->reminder->to)
            ->notify(new EventReminderNotification($event->reminder));
    }
}
