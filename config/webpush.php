<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web Push (VAPID)
    |--------------------------------------------------------------------------
    |
    | Config expected by laravel-notification-channels/webpush. Generate a
    | key pair with `php artisan webpush:vapid` and expose only the public
    | key to clients (see PushSubscriptionController@vapidPublicKey).
    |
    */

    'vapid' => [
        'subject'    => env('VAPID_SUBJECT', env('APP_URL')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

];
