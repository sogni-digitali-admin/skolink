<?php

namespace App\Listeners;

use App\Events\AbsentReminderMailEvent;
use App\Notifications\AbsentReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class AbsentReminderMailEventListener implements ShouldQueue
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
     * Sends the absentee alert through the notification ladder
     * (web push -> WhatsApp -> mail -> database) to the parent user
     * matching the reminder recipient. Falls back to an on-demand
     * mail notification when no user account matches the address.
     *
     * @param  AbsentReminderMailEvent  $event
     * @return void
     */
    public function handle(AbsentReminderMailEvent $event)
    {
        $user = $event->reminder->user;

        if ($user) {
            $user->notify(new AbsentReminderNotification($event->reminder));

            return;
        }

        Notification::route('mail', $event->reminder->to)
            ->notify(new AbsentReminderNotification($event->reminder));
    }
}
